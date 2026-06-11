<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\CategorySelectorServiceInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportServiceInterface;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

#[AsCommand(
    name: 'topdata:demo-data-importer:import-demo-products',
    description: 'Import demo products into the shop'
)]
class ImportDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoDataImportServiceInterface $demoDataImportService,
        private readonly CategorySelectorServiceInterface $categorySelectorService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
        $this->addOption('category-id', null, InputOption::VALUE_REQUIRED, 'Import products into a specific category by ID');
        $this->addOption('no-category', null, InputOption::VALUE_NONE, 'Import products without assigning them to any category');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        CliLogger::warning('This will import demo products into your shop.');

        $categoryId = $input->getOption('category-id');
        $noCategory = $input->getOption('no-category');

        if ($categoryId !== null && $noCategory) {
            CliLogger::error('Options --category-id and --no-category cannot be used together.');
            return Command::FAILURE;
        }

        if ($categoryId === null && !$noCategory) {
            $choices = $this->categorySelectorService->getCategoryChoices();
            if (empty($choices)) {
                CliLogger::warning('No categories found in the system. Continuing without category assignment.');
            } else {
                CliLogger::section('Category Selection');
                CliLogger::writeln('Please select a category to import the demo products into:');
                $categoryId = CliLogger::getCliStyle()->choice('Select category', $choices, array_key_first($choices));
            }
        }

        $force = $input->getOption('force');
        if (!$force && !CliLogger::getCliStyle()->confirm('Are you sure you want to proceed?', true)) {
            CliLogger::writeln('Aborted.');
            return Command::FAILURE;
        }

        $result = $this->demoDataImportService->installDemoData('demo-products.csv', $categoryId);

        if (isset($result['importedProducts']) && is_array($result['importedProducts'])) {
            CliLogger::section('Imported Articles');

            $tableHeaders = ['Product Number', 'Name', 'EAN', 'MPN'];
            $tableRows = [];

            foreach ($result['importedProducts'] as $product) {
                $tableRows[] = [
                    $product['productNumber'] ?? '',
                    $product['name'] ?? '',
                    $product['ean'] ?? '',
                    $product['mpn'] ?? ''
                ];
            }

            CliLogger::getCliStyle()->table($tableHeaders, $tableRows);
            CliLogger::writeln('');
        }

        $categoryName = $categoryId ? $this->categorySelectorService->getCategoryName($categoryId) : null;

        $successMessage = 'Demo data imported successfully!';
        if ($categoryName) {
            $successMessage .= sprintf(' Products have been assigned to category: %s', $categoryName);
        } elseif ($noCategory) {
            $successMessage .= ' Products have been imported without category assignment.';
        } else {
            $successMessage .= ' Products have been imported.';
        }

        CliLogger::success($successMessage);
        CliLogger::writeln("Consider running the <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }
}
