<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImportSW6\Service\DemoDataImportService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to import products from a CSV file into Shopware 6
 * 
 * 11/2024 created
 */
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
        $this
            ->setName('topdata:demo-data-importer:import-demo-products')
            ->setDescription('Import demo products into the shop');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->demoDataImportService->installDemoData();

        dump($result);

        return Command::SUCCESS;
    }

}
