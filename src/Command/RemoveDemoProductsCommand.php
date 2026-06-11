<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for clearing the shop of all demo-tagged products.
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:remove-demo-products',
    description: 'Removes all demo products that were imported by this plugin.'
)]
class RemoveDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoProductService $productService
    ) {
        parent::__construct();
    }

    /**
     * Configures command flags.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and delete products immediately.');
    }

    /**
     * Maps global styles to logging helper.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Executes the deletion routine.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $products = $this->productService->getDemoProducts($context);

        if ($products->getTotal() === 0) {
            CliLogger::success('No demo products found to remove.');
            $this->done();
            return Command::SUCCESS;
        }

        CliLogger::warning(sprintf('%d demo products will be permanently deleted.', $products->getTotal()));
        CliLogger::section('Products to be removed');

        $tableHeaders = ['Product Number', 'Name', 'EAN', 'MPN'];
        $tableRows = [];

        foreach ($products->getEntities() as $product) {
            $tableRows[] = [
                $product->getProductNumber() ?? '',
                $product->getTranslation('name') ?? '',
                $product->getEan() ?? '',
                $product->getManufacturerNumber() ?? '',
            ];
        }

        CliLogger::getCliStyle()->table($tableHeaders, $tableRows);
        CliLogger::writeln('');

        $force = $input->getOption('force');
        if (!$force && !CliLogger::getCliStyle()->confirm('Are you sure you want to proceed?', true)) {
            CliLogger::writeln('Aborted.');
            return Command::FAILURE;
        }

        $this->productService->removeDemoProducts($context);

        CliLogger::success(sprintf('Successfully deleted %d demo products.', $products->getTotal()));
        $this->done();

        return Command::SUCCESS;
    }
}
