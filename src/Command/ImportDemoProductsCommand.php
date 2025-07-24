<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to import products from a CSV file into Shopware 6
 * 
 * 11/2024 created
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:import-demo-products',
    description: 'Import demo products into the shop'
)]
class ImportDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoDataImportService $demoDataImportService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cliStyle->warning('This will import demo products into your shop.');
        
        $force = $input->getOption('force');
        if (!$force && !$this->cliStyle->confirm('Are you sure you want to proceed?', true)) {
            $this->cliStyle->writeln('Aborted.');
            return Command::FAILURE;
        }

        $result = $this->demoDataImportService->installDemoData();

        if (isset($result['importedProducts']) && is_array($result['importedProducts'])) {
            $this->cliStyle->section('Imported Articles');
            
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
            
            $this->cliStyle->table($tableHeaders, $tableRows);
            $this->cliStyle->newLine();
        }

        $this->cliStyle->success($result['additionalInfo'] ?? 'Demo data imported successfully!');
        $this->cliStyle->writeln("Consider to run <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }
}
