<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->demoDataImportService->installDemoData();

        dump($result);

        $this->cliStyle->success('Demo data imported successfully!');
        $this->cliStyle->writeln("Consider to run <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }

}
