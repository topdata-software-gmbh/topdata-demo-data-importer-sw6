<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\Service\ProductService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to import products from a CSV file into Shopware 6
 * 
 * 11/2024 moved from TopdataConnectorSW6::ProductsCommand to TopdataDemoDataImporterSW6::ImportProductsCsvCommand
 */
class ImportProductsCsvCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly ProductService $productService
    )
    {
        parent::__construct();
    }

    /**
     * Configure the command options
     *
     * options for indexes of the columns in the CSV file:
     *     - Required options: file, name, number
     *     - Optional options: start, end, wsid, description, ean, mpn, brand, divider, trim
     */
    protected function configure(): void
    {
        $this
            ->setName('topdata:demo-data-importer:import-products-csv')
            ->setDescription('Import products from a CSV file')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to the CSV file')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start line number for import')
            ->addOption('end', null, InputOption::VALUE_OPTIONAL, 'End line number for import')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Column number for product name')
            ->addOption('number', null, InputOption::VALUE_REQUIRED, 'Column number for product number')
            ->addOption('wsid', null, InputOption::VALUE_OPTIONAL, 'Column number for Topdata webservice ID')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Column number for product description')
            ->addOption('ean', null, InputOption::VALUE_OPTIONAL, 'Column number for EAN')
            ->addOption('mpn', null, InputOption::VALUE_OPTIONAL, 'Column number for MPN')
            ->addOption('brand', null, InputOption::VALUE_OPTIONAL, 'Column number for brand')
            ->addOption('divider', null, InputOption::VALUE_OPTIONAL, 'CSV column delimiter (default: ;)')
            ->addOption('trim', null, InputOption::VALUE_OPTIONAL, 'Character to trim from values (default: ")');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getOption('file');
        if (!$file) {
            echo "add file!\n";

            return Command::FAILURE;
        }

        echo $file . "\n";

        $columnMapping = [
            'number' => (int)$input->getOption('number'),
            'name' => (int)$input->getOption('name'),
            'wsid' => $input->getOption('wsid') ? (int)$input->getOption('wsid') : null,
            'description' => $input->getOption('description') ? (int)$input->getOption('description') : null,
            'ean' => $input->getOption('ean') ? (int)$input->getOption('ean') : null,
            'mpn' => $input->getOption('mpn') ? (int)$input->getOption('mpn') : null,
            'brand' => $input->getOption('brand') ? (int)$input->getOption('brand') : null,
        ];

        $csvConfig = new CsvConfiguration(
            $input->getOption('divider') ?? ';',
            $input->getOption('trim') ?? '"',
            (int)($input->getOption('start') ?? 1),
            $input->getOption('end') ? (int)$input->getOption('end') : null,
            $columnMapping
        );

        try {
            $products = $this->productService->parseProductsFromCsv($file, $csvConfig);
        } catch (\RuntimeException $e) {
            $this->cliStyle->error($e->getMessage());
            return 3;
        }

        $this->cliStyle->writeln('Products in file: ' . count($products));

        $products = $this->productService->clearExistingProductsByProductNumber($products);

        $this->cliStyle->writeln('Products not added yet: ' . count($products));

        if (count($products)) {
            $products = $this->productService->formProductsArray($products);
        } else {
            echo 'no products found';

            return 4;
        }
        $prods = array_chunk($products, 50);
        foreach ($prods as $key => $prods_chunk) {
            echo 'adding ' . ($key * 50 + count($prods_chunk)) . ' of ' . count($products) . " products...\n";
            $this->productService->createProducts($prods_chunk);
        }

        return 0;
    }

}
