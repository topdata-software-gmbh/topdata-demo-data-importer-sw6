<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\Service\ProductService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to import products from a CSV file into Shopware 6.
 *
 * This command allows importing product data from a CSV file into a Shopware 6 instance.
 * It provides options to configure the CSV file path, column mappings, and import range.
 *
 * 11/2024 moved from TopdataConnectorSW6::ProductsCommand to TopdataDemoDataImporterSW6::ImportProductsCsvCommand
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:import-products-csv',
    description: 'Import products from a CSV file',
)]
class ImportProductsCsvCommand extends AbstractTopdataCommand
{
    /**
     * @param ProductService $productService
     */
    public function __construct(
        private readonly ProductService $productService
    )
    {
        parent::__construct();
    }

    /**
     * Configures the command with options for CSV file path, column mappings, and import range.
     *
     * options for indexes of the columns in the CSV file:
     *     - Required options: file, name, number
     *     - Optional options: start, end, wsid, description, ean, mpn, brand, divider, trim
     */
    protected function configure(): void
    {
            $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to the CSV file');
            $this->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start line number for import');
            $this->addOption('end', null, InputOption::VALUE_OPTIONAL, 'End line number for import');
            $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Column number for product name');
            $this->addOption('number', null, InputOption::VALUE_REQUIRED, 'Column number for product number');
            $this->addOption('wsid', null, InputOption::VALUE_OPTIONAL, 'Column number for Topdata webservice ID');
            $this->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Column number for product description');
            $this->addOption('ean', null, InputOption::VALUE_OPTIONAL, 'Column number for EAN');
            $this->addOption('mpn', null, InputOption::VALUE_OPTIONAL, 'Column number for MPN');
            $this->addOption('brand', null, InputOption::VALUE_OPTIONAL, 'Column number for brand');
            $this->addOption('divider', null, InputOption::VALUE_OPTIONAL, 'CSV column delimiter (default: ;)');
            $this->addOption('trim', null, InputOption::VALUE_OPTIONAL, 'Character to trim from values (default: ")');
    }

    /**
     * Executes the command to import products from a CSV file.
     *
     * @param InputInterface $input The input interface.
     * @param OutputInterface $output The output interface.
     * @return int 0 if successful, otherwise a non-zero value.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // ---- Get the file path from the input options
        $file = $input->getOption('file');
        if (!$file) {
            echo "add file!\n";

            return Command::FAILURE;
        }

        echo $file . "\n";

        // ---- Define the column mapping based on the input options
        $columnMapping = [
            'number'      => (int)$input->getOption('number'),
            'name'        => (int)$input->getOption('name'),
            'wsid'        => $input->getOption('wsid') ? (int)$input->getOption('wsid') : null,
            'description' => $input->getOption('description') ? (int)$input->getOption('description') : null,
            'ean'         => $input->getOption('ean') ? (int)$input->getOption('ean') : null,
            'mpn'         => $input->getOption('mpn') ? (int)$input->getOption('mpn') : null,
            'brand'       => $input->getOption('brand') ? (int)$input->getOption('brand') : null,
        ];

        // ---- Create a CsvConfiguration object based on the input options
        $csvConfig = new CsvConfiguration(
            $input->getOption('divider') ?? ';',
            $input->getOption('trim') ?? '"',
            (int)($input->getOption('start') ?? 1),
            $input->getOption('end') ? (int)$input->getOption('end') : null,
            $columnMapping
        );

        // ---- Parse the products from the CSV file
        try {
            $products = $this->productService->parseProductsFromCsv($file, $csvConfig);
        } catch (\RuntimeException $e) {
            $this->cliStyle->error($e->getMessage());
            return 3;
        }

        $this->cliStyle->writeln('Products in file: ' . count($products));

        // ---- Clear existing products by product number
        $products = $this->productService->clearExistingProductsByProductNumber($products);

        $this->cliStyle->writeln('Products not added yet: ' . count($products));

        // ---- Form the products array
        if (count($products)) {
            $products = $this->productService->formProductsArray($products);
        } else {
            echo 'no products found';

            return 4;
        }

        // ---- Chunk the products array and create the products
        $prods = array_chunk($products, 50);
        foreach ($prods as $key => $prods_chunk) {
            echo 'adding ' . ($key * 50 + count($prods_chunk)) . ' of ' . count($products) . " products...\n";
            $this->productService->createProducts($prods_chunk);
        }

        return 0;
    }

}