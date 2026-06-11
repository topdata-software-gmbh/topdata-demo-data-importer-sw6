<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for importing custom product lists from CSV file layouts.
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:import-products-csv',
    description: 'Import products from a CSV file',
)]
class ImportProductsCsvCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoProductServiceInterface $productService
    ) {
        parent::__construct();
    }

    /**
     * Configures the input arguments.
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
     * Initializes the command environment.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Main CLI routine to run custom file parser imports.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getOption('file');
        if (!$file) {
            CliLogger::error('Missing required option: --file');
            return Command::FAILURE;
        }

        CliLogger::info('Processing CSV file: ' . $file);

        $columnMapping = [
            'number'      => (int)$input->getOption('number'),
            'name'        => (int)$input->getOption('name'),
            'wsid'        => $input->getOption('wsid') ? (int)$input->getOption('wsid') : null,
            'description' => $input->getOption('description') ? (int)$input->getOption('description') : null,
            'ean'         => $input->getOption('ean') ? (int)$input->getOption('ean') : null,
            'mpn'         => $input->getOption('mpn') ? (int)$input->getOption('mpn') : null,
            'brand'       => $input->getOption('brand') ? (int)$input->getOption('brand') : null,
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
            CliLogger::error($e->getMessage());
            return Command::FAILURE;
        }

        CliLogger::info('Products in file: ' . count($products));

        $products = $this->productService->clearExistingProductsByProductNumber($products);

        CliLogger::info('Products not added yet: ' . count($products));

        if (count($products)) {
            $products = $this->productService->formProductsArray($products);
        } else {
            CliLogger::warning('No products found to add.');
            return Command::SUCCESS;
        }

        $prods = array_chunk($products, 50);
        foreach ($prods as $key => $prods_chunk) {
            CliLogger::info(sprintf('Adding %d of %d products...', $key * 50 + count($prods_chunk), count($products)));
            $this->productService->createProducts($prods_chunk);
        }

        CliLogger::success('Products successfully processed.');

        return Command::SUCCESS;
    }
}
