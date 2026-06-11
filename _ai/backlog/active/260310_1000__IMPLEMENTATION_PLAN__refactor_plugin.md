---
filename: "_ai/backlog/active/260310_1000__IMPLEMENTATION_PLAN__refactor_plugin.md"
title: "Refactor Demo Data Importer Plugin to Clean Architecture and Modern Standards"
createdAt: 2026-03-10 10:00
updatedAt: 2026-03-10 10:00
status: draft
priority: high
tags: [refactoring, shopware, commands, php8, code-quality]
estimatedComplexity: moderate
documentType: IMPLEMENTATION_PLAN
---

## Problem Description
The current codebase of the `topdata-demo-data-importer` plugin contains several styling inconsistencies, legacy patterns, and violations of project conventions:
1. **Direct CLI Output Violations:** Some commands use raw `echo` statements or interact directly with Symfony's `$this->cliStyle` helpers instead of routing through the unified `\Topdata\TopdataFoundationSW6\Util\CliLogger` utility.
2. **Naming Convention Deviations:** Private helper methods in several classes (such as `ImportDemoProductsCommand` and `ProductCsvReader`) do not start with a leading underscore (`_`).
3. **Redundant or Missing PHPDoc Tags:** Several classes lack descriptive docblocks, and many methods contain redundant PHPDoc annotations (`@param` or `@return` tags that merely duplicate the native PHP type hints without adding value).
4. **Consistency & Maintainability:** Code style is somewhat fragmented with dead code blocks and legacy comments.

## Executive Summary
This implementation plan provides a structured approach to modernizing the entire plugin codebase. The refactoring will achieve the following:
- Ensure all private methods start with an underscore (`_`).
- Convert all command console logging to use `\Topdata\TopdataFoundationSW6\Util\CliLogger`.
- Standardize all PHP docblocks: adding descriptive headers, enforcing parameter type hints, and removing redundant annotations.
- Keep the plugin fully compatible with Shopware 6.5, 6.6, and 6.7 (Symfony 7.4).

## Project Environment
- **Project Name:** SW6.7 Plugin (Topdata Demo Data Importer)
- **Backend Root:** `src`
- **PHP Version:** `~8.2.0 || ~8.3.0 || ~8.4.0`

---

## Detailed Implementation Plan

### Phase 1: Core Coding Standards & Private Method Standardisation
In this phase, we rename all private methods across all services, commands, and controllers to start with a leading underscore (`_`). We also audit docblocks to add class descriptions, explain method behaviors, and remove redundant annotations.

#### Task 1.1: Refactor `ProductCsvReader`
**File to Edit:** `src/Service/ProductCsvReader.php` [MODIFY]
- Rename private method `processFile` to `_processFile`.
- Rename private method `mapRowToProduct` to `_mapRowToProduct`.
- Update docblocks to describe what the class and methods do without redundant `@param` or `@return` declarations.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use RuntimeException;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

/**
 * Service for parsing and reading product data from CSV files.
 * This helper processes CSV handles and transforms raw data rows into structured product arrays.
 */
class ProductCsvReader
{
    /**
     * Reads and parses product data from a CSV file based on the provided configuration.
     *
     * @throws RuntimeException if the file cannot be accessed or is invalid.
     */
    public function readProducts(string $filePath, CsvConfiguration $config): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('File not found: ' . $filePath);
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Could not open file: ' . $filePath);
        }

        try {
            return $this->_processFile($handle, $config);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Iterates through the file rows and extracts matching product data.
     */
    private function _processFile($handle, CsvConfiguration $config): array
    {
        $products = [];
        $lineNumber = 0;
        $mapping = $config->getColumnMapping();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            if ($lineNumber < $config->getStartLine()) {
                continue;
            }

            if ($config->getEndLine() !== null && $lineNumber > $config->getEndLine()) {
                break;
            }

            $values = array_map(
                fn($val) => trim($val, $config->getEnclosure()),
                explode($config->getDelimiter(), $line)
            );

            if (!isset($values[$mapping['number']]) || !isset($values[$mapping['name']])) {
                continue;
            }

            $products[$values[$mapping['number']]] = $this->_mapRowToProduct($values, $mapping);
        }

        return $products;
    }

    /**
     * Maps an array of raw row values to a structured product.
     */
    private function _mapRowToProduct(array $values, array $mapping): array
    {
        $product = [
            'productNumber' => $values[$mapping['number']],
            'name'          => $values[$mapping['name']],
        ];

        $optionalFields = [
            'wsid'        => 'topDataId',
            'description' => 'description',
            'ean'         => 'ean',
            'mpn'         => 'mpn',
            'brand'       => 'brand'
        ];

        foreach ($optionalFields as $csvField => $productField) {
            if (isset($mapping[$csvField]) && isset($values[$mapping[$csvField]])) {
                $product[$productField] = $values[$mapping[$csvField]];
            }
        }

        return $product;
    }
}
```

#### Task 1.2: Refactor `DemoProductService`
**File to Edit:** `src/Service/DemoProductService.php` [MODIFY]
- Review existing private methods `_getTaxId` and `_getStorefrontSalesChannel` (already conform with leading underscore).
- Ensure all method signatures are strictly type-hinted.
- Update class and method docblocks. Remove redundant type annotations.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;
use Topdata\TopdataFoundationSW6\Service\LocaleHelperService;

/**
 * Service managing demo product lifetime, search, format mapping, creation, and database removal.
 */
class DemoProductService
{
    private string $systemDefaultLocaleCode;
    private Context $context;

    public function __construct(
        private readonly EntityRepository    $productRepository,
        private readonly EntityRepository    $productManufacturerRepository,
        private readonly Connection          $connection,
        private readonly ProductCsvReader    $productCsvReader,
        private readonly LocaleHelperService $localeHelperService,
    ) {
        $this->context = Context::createDefaultContext();
        $this->systemDefaultLocaleCode = $this->localeHelperService->getLocaleCodeOfSystemLanguage();
    }

    /**
     * Parses product datasets from a raw CSV source using standard configurations.
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array
    {
        return $this->productCsvReader->readProducts($filePath, $config);
    }

    /**
     * Constructs a Shopware-compliant structural array of products ready for execution payload.
     */
    public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array
    {
        $output = [];
        $taxId = $this->_getTaxId();
        $storefrontSalesChannel = $this->_getStorefrontSalesChannel();
        $priceTax = $price * 1.19;

        foreach ($input as $in) {
            $prod = [
                'id'               => Uuid::randomHex(),
                'productNumber'    => $in['productNumber'],
                'active'           => true,
                'taxId'            => $taxId,
                'stock'            => 10,
                'shippingFree'     => false,
                'purchasePrice'    => $priceTax,
                'displayInListing' => true,
                'name'             => [
                    $this->systemDefaultLocaleCode => $in['name'],
                ],
                'price'            => [[
                    'net'        => $price,
                    'gross'      => $priceTax,
                    'linked'     => true,
                    'currencyId' => Defaults::CURRENCY,
                ]],
                'visibilities'     => [
                    [
                        'salesChannelId' => $storefrontSalesChannel,
                        'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
                'customFields' => [
                    TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT => true,
                ],
            ];

            if ($categoryId) {
                $prod['categories'] = [
                    ['id' => $categoryId],
                ];
            }

            if (isset($in['description'])) {
                $prod['description'] = [
                    $this->systemDefaultLocaleCode => $in['description'],
                ];
            }

            if (isset($in['mpn'])) {
                $prod['manufacturerNumber'] = $in['mpn'];
            }

            if (isset($in['ean'])) {
                $prod['ean'] = $in['ean'];
            }

            if (isset($in['topDataId'])) {
                $prod['topdata'] = [
                    'topDataId' => $in['topDataId'],
                ];
            }

            $output[] = $prod;
        }

        return $output;
    }

    /**
     * Performs persistence operations to write product details to the repository.
     */
    public function createProducts(array $products): void
    {
        $this->productRepository->create($products, $this->context);
    }

    /**
     * Resolves the list of existing products flagged as demo content.
     */
    public function getDemoProducts(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT, true));
        $criteria->addAssociation('manufacturer');
        $criteria->setLimit(500);

        return $this->productRepository->search($criteria, $context);
    }

    /**
     * Deletes all imported demo products from the system database.
     */
    public function removeDemoProducts(Context $context): void
    {
        $ids = $this->getDemoProducts($context)->getIds();

        if (empty($ids)) {
            return;
        }

        $this->productRepository->delete(
            array_map(fn(string $id) => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * Prevents database duplication by matching and skipping existing product numbers.
     */
    public function clearExistingProductsByProductNumber(array $products): array
    {
        $rezProducts = $products;
        $product_arrays = array_chunk($products, 50, true);
        foreach ($product_arrays as $prods) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('productNumber', array_keys($prods)));
            $foundedProducts = $this->productRepository->search($criteria, $this->context)->getEntities();
            foreach ($foundedProducts as $foundedProd) {
                unset($rezProducts[$foundedProd->getProductNumber()]);
            }
        }

        return $rezProducts;
    }

    /**
     * Returns the matching standard system tax rate.
     */
    private function _getTaxId(): string
    {
        $result = $this->connection->executeQuery('
            SELECT LOWER(HEX(COALESCE(
                (SELECT `id` FROM `tax` WHERE tax_rate = "19.00" LIMIT 1),
                (SELECT `id` FROM `tax`  LIMIT 1)
            )))
        ')->fetchOne();

        if (!$result) {
            throw new \RuntimeException('No tax found, please make sure that basic data is available by running the migrations.');
        }

        return (string)$result;
    }

    /**
     * Returns the default storefront sales channel UUID.
     */
    private function _getStorefrontSalesChannel(): string
    {
        $result = $this->connection->executeQuery('
            SELECT LOWER(HEX(`id`))
            FROM `sales_channel`
            WHERE `type_id` = 0x' . Defaults::SALES_CHANNEL_TYPE_STOREFRONT . '
            ORDER BY `created_at` ASC            
        ')->fetchOne();

        if (!$result) {
            throw new \RuntimeException('No sale channel found.');
        }

        return (string)$result;
    }
}
```

#### Task 1.3: Refactor `DemoDataImportService`
**File to Edit:** `src/Service/DemoDataImportService.php` [MODIFY]
- Align docblocks and remove redundant annotations.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

/**
 * High-level orchestration service reading and importing bundled product configurations.
 */
class DemoDataImportService
{
    private ?int $columnNumber = null;
    private ?int $columnName = null;
    private ?int $columnEAN = null;
    private ?int $columnMPN = null;
    private string $divider;
    private string $trim;

    public function __construct(
        private readonly DemoProductService $productService
    ) {
    }

    /**
     * Reads a predefined CSV file from the resources directory and imports the products.
     */
    public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array
    {
        $this->divider = ';';
        $this->trim = '"';
        if (!$filename) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo data not found!',
            ];
        }

        $file = __DIR__ . '/../Resources/demo-data/' . $filename;
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo not accessible!',
            ];
        }

        $line = fgets($handle);
        if ($line === false) {
            return [
                'success'        => false,
                'additionalInfo' => 'file is empty!',
            ];
        }

        $values = explode($this->divider, $line);

        foreach ($values as $key => $val) {
            $val = trim($val);
            if ($val === 'article_no') {
                $this->columnNumber = $key;
            }
            if ($val === 'short_desc') {
                $this->columnName = $key;
            }
            if ($val === 'ean') {
                $this->columnEAN = $key;
            }
            if ($val === 'oem') {
                $this->columnMPN = $key;
            }
        }

        if (is_null($this->columnNumber)) {
            return [
                'success'        => false,
                'additionalInfo' => 'article_no column not exists!',
            ];
        }

        if (is_null($this->columnName)) {
            return [
                'success'        => false,
                'additionalInfo' => 'short_desc column not exists!',
            ];
        }

        if (is_null($this->columnEAN)) {
            return [
                'success'        => false,
                'additionalInfo' => 'ean column not exists!',
            ];
        }

        if (is_null($this->columnMPN)) {
            return [
                'success'        => false,
                'additionalInfo' => 'oem column not exists!',
            ];
        }

        $products = [];

        while (($line = fgets($handle)) !== false) {
            $values = explode($this->divider, $line);
            foreach ($values as $key => $val) {
                $values[$key] = trim($val, $this->trim);
            }
            $products[$values[$this->columnNumber]] = [
                'productNumber' => trim($values[$this->columnNumber]),
                'name'          => trim($values[$this->columnName]),
                'ean'           => trim($values[$this->columnEAN]),
                'mpn'           => trim($values[$this->columnMPN]),
            ];
        }

        fclose($handle);

        $products = $this->productService->clearExistingProductsByProductNumber($products);
        if (count($products)) {
            $products = $this->productService->formProductsArray($products, 100000.0, $categoryId);
        } else {
            return [
                'success'        => true,
                'additionalInfo' => 'Nothing to add',
            ];
        }

        $this->productService->createProducts($products);

        return [
            'success'          => true,
            'additionalInfo'   => count($products) . ' products has been added',
            'importedProducts' => array_map(function ($product) {
                return [
                    'productNumber' => $product['productNumber'],
                    'name'          => $product['name'][array_key_first($product['name'])],
                    'ean'           => $product['ean'] ?? null,
                    'mpn'           => $product['manufacturerNumber'] ?? null,
                ];
            }, $products)
        ];
    }
}
```

#### Task 1.4: Refactor `CsvConfiguration`
**File to Edit:** `src/DTO/CsvConfiguration.php` [MODIFY]
- Simplify docblocks. Getters and setters do not require explicit docblocks unless explaining complex functionality.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\DTO;

/**
 * Data Transfer Object storing the custom parsing parameters for CSV product imports.
 */
class CsvConfiguration
{
    /**
     * @param array<string, int|null> $columnMapping Maps property names to CSV column indexes.
     */
    public function __construct(
        private readonly string $delimiter,
        private readonly string $enclosure,
        private readonly int    $startLine,
        private readonly ?int   $endLine,
        private readonly array  $columnMapping
    ) {
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    public function getStartLine(): int
    {
        return $this->startLine;
    }

    public function getEndLine(): ?int
    {
        return $this->endLine;
    }

    /**
     * @return array<string, int|null>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }
}
```

---

### Phase 2: Refactoring Commands to Use `CliLogger`
This phase cleans up custom CLI feedback logic. We route all interactive prompts, confirmations, formatting tables, error banners, and general progress updates exclusively through `\Topdata\TopdataFoundationSW6\Util\CliLogger`.

#### Task 2.1: Refactor `ImportDemoProductsCommand`
**File to Edit:** `src/Command/ImportDemoProductsCommand.php` [MODIFY]
- Rename helper private method `getCategoryName` to `_getCategoryName`.
- Rename helper private method `buildBreadcrumb` to `_buildBreadcrumb`.
- Use `CliLogger` for messages, sections, tables, warnings, and success indications.
- Set up SymfonyStyle using `CliLogger::setCliStyle($this->cliStyle)` in the `initialize` hook.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for importing the standard bundled product CSV dataset.
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:import-demo-products',
    description: 'Import demo products into the shop'
)]
class ImportDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
        private readonly EntityRepository $categoryRepository
    ) {
        parent::__construct();
    }

    /**
     * Configures the input options.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
        $this->addOption('category-id', null, InputOption::VALUE_REQUIRED, 'Import products into a specific category by ID');
        $this->addOption('no-category', null, InputOption::VALUE_NONE, 'Import products without assigning them to any category');
    }

    /**
     * Links custom cliStyle output to the global CliLogger service wrapper.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Core execution logic of the command.
     */
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
            $categoryId = $this->_getCategoryFromInteractiveChoice();
            if ($categoryId === null) {
                CliLogger::error('No category selected. Aborting.');
                return Command::FAILURE;
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

        $categoryName = $categoryId ? $this->_getCategoryName($categoryId) : null;
        
        $successMessage = 'Demo data imported successfully!';
        if ($categoryName) {
            $successMessage .= sprintf(' Products have been assigned to category: %s', $categoryName);
        } elseif ($noCategory) {
            $successMessage .= ' Products have been imported without category assignment.';
        } else {
            $successMessage .= ' Products have been imported.';
        }
        
        CliLogger::success($successMessage);
        CliLogger::writeln("Consider to run <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }

    /**
     * Fetches a category name by UUID.
     */
    private function _getCategoryName(string $categoryId): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();
        
        return $category instanceof CategoryEntity ? $category->getName() : null;
    }

    /**
     * Interactively asks the user to pick a category.
     */
    private function _getCategoryFromInteractiveChoice(): ?string
    {
        $criteria = new Criteria();
        $criteria->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('name'));
        $criteria->addAssociation('parent');
        $criteria->setLimit(100);

        $categories = $this->categoryRepository->search($criteria, Context::createDefaultContext());
        
        if ($categories->count() === 0) {
            CliLogger::warning('No categories found in the system.');
            return null;
        }

        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getId()] = $category;
        }

        $categoriesData = [];
        foreach ($categories as $category) {
            /** @var CategoryEntity $category */
            $breadcrumb = $this->_buildBreadcrumb($category, $categoryMap);
            $depth = count($breadcrumb);
            $displayName = implode(' > ', $breadcrumb);
            
            $categoriesData[] = [
                'category' => $category,
                'breadcrumb' => $breadcrumb,
                'depth' => $depth,
                'displayName' => $displayName,
            ];
        }

        usort($categoriesData, function($a, $b) {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] - $b['depth'];
            }
            return strcmp($a['displayName'], $b['displayName']);
        });

        $choices = [];
        foreach ($categoriesData as $data) {
            $category = $data['category'];
            $choices[$category->getId()] = $data['displayName'];
        }

        CliLogger::section('Category Selection');
        CliLogger::writeln('Please select a category to import the demo products into:');
        
        return CliLogger::getCliStyle()->choice(
            'Select category',
            $choices,
            array_key_first($choices)
        );
    }

    /**
     * Helper to build visual string tree paths of parent-child category relations.
     */
    private function _buildBreadcrumb(CategoryEntity $category, array $categoryMap): array
    {
        $breadcrumb = [];
        $current = $category;
        
        while ($current !== null) {
            array_unshift($breadcrumb, $current->getName() ?? 'Unnamed Category');
            
            $parentId = $current->getParentId();
            if ($parentId && isset($categoryMap[$parentId])) {
                $current = $categoryMap[$parentId];
            } else {
                $current = null;
            }
        }
        
        return $breadcrumb;
    }
}
```

#### Task 2.2: Refactor `ImportProductsCsvCommand`
**File to Edit:** `src/Command/ImportProductsCsvCommand.php` [MODIFY]
- Replace raw `echo` statements with `CliLogger::info`, `CliLogger::error`, or `CliLogger::warning`.
- Clean up docblocks and redundant `@param`/`@return` annotations.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductService;
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
        private readonly DemoProductService $productService
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
```

#### Task 2.3: Refactor `RemoveDemoProductsCommand`
**File to Edit:** `src/Command/RemoveDemoProductsCommand.php` [MODIFY]
- Replace raw `$this->cliStyle` calls with `CliLogger` standard API.
- Ensure correct initialization hook is active.

```php
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
```

#### Task 2.4: Refactor `UseWebserviceDemoCredentialsCommand`
**File to Edit:** `src/Command/UseWebserviceDemoCredentialsCommand.php` [MODIFY]
- Replace raw `$this->cliStyle` calls with `CliLogger`.
- Set up class and helper private methods docblocks properly.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Service\PluginHelperService;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for overwriting credentials in the configuration store to set up connections to the Topdata staging servers.
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:use-webservice-demo-credentials',
    description: 'Use the demo credentials for the Topdata webservice. It updates the system configuration with the demo credentials.',
)]
class UseWebserviceDemoCredentialsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly Connection          $connection,
        private readonly PluginHelperService $pluginHelperService
    ) {
        parent::__construct();
    }

    /**
     * Standard flag configuration.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overriding of credentials that already exist in the database');
    }

    /**
     * Initializes the console output adapter.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Script routine to inject values.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool)$input->getOption('force');

        if (!$this->pluginHelperService->isWebserviceConnectorPluginAvailable()) {
            CliLogger::error('The Topdata Webservice Connector plugin is not installed.');
            return Command::FAILURE;
        }

        if ($this->_doCredentialsExist()) {
            if (!$force) {
                CliLogger::error('Credentials already exist. Use --force to override.');
                return Command::FAILURE;
            }
            $this->_deleteExistingCredentials();
        }

        $this->_insertCredentials();
        CliLogger::success('Credentials set');

        return Command::SUCCESS;
    }

    /**
     * Clears credentials config records.
     */
    private function _deleteExistingCredentials(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config 
            WHERE configuration_key IN (
                :username,
                :apiKey,
                :apiSalt
            )', [
            'username' => 'TopdataConnectorSW6.config.apiUid',
            'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
            'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
        ]);
    }

    /**
     * Injects standard API configuration staging blocks.
     */
    private function _insertCredentials(): void
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $configs = [
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiUid',
                'configuration_value' => json_encode(['_value' => '6']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiPassword',
                'configuration_value' => json_encode(['_value' => 'nTI9kbsniVWT13Ns']),
                'created_at'          => $now
            ],
            [
                'id'                  => Uuid::randomBytes(),
                'configuration_key'   => 'TopdataConnectorSW6.config.apiSecurityKey',
                'configuration_value' => json_encode(['_value' => 'oateouq974fpby5t6ldf8glzo85mr9t6aebozrox']),
                'created_at'          => $now
            ],
        ];

        foreach ($configs as $config) {
            $this->connection->insert('system_config', $config);
        }
    }

    /**
     * Resolves whether relevant database settings are already present.
     */
    private function _doCredentialsExist(): bool
    {
        $existingCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM system_config 
                WHERE configuration_key IN (
                    :username,
                    :apiKey,
                    :apiSalt
                )',
            [
                'username' => 'TopdataConnectorSW6.config.apiUid',
                'apiKey'   => 'TopdataConnectorSW6.config.apiKey',
                'apiSalt'  => 'TopdataConnectorSW6.config.apiSalt',
            ]
        );

        return $existingCount > 0;
    }
}
```

---

### Phase 3: Controller & Plugin Base Refactoring
We clean up structural dependencies, type hints, and code styling on the API Controller and the main entry class to guarantee complete adherence to style guidelines.

#### Task 3.1: Refactor `TopdataDemoDataAdminApiController`
**File to Edit:** `src/Controller/TopdataDemoDataAdminApiController.php` [MODIFY]
- Align class and method docblocks. Ensure constructor uses property promotion.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductService;

/**
 * Administration API controller providing endpoints for demo data operations.
 */
#[Route(
    defaults: ['_routeScope' => ['administration']],
)]
class TopdataDemoDataAdminApiController extends AbstractController
{
    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
        private readonly DemoProductService        $productService,
    ) {
    }

    /**
     * Installs products from the default bundled demo CSV.
     */
    #[Route(
        path: '/api/topdata-demo-data/install-demodata',
        methods: ['POST']
    )]
    public function installDemoData(): JsonResponse
    {
        $result = $this->demoDataImportService->installDemoData();

        return new JsonResponse($result);
    }

    /**
     * Returns statistics of existing demo products in store.
     */
    #[Route(
        path: '/api/topdata-demo-data/status',
        name: 'api.topdata_demo_data.status',
        methods: ['GET']
    )]
    public function getDemoDataStatus(): JsonResponse
    {
        $demoProductsResult = $this->productService->getDemoProducts(Context::createDefaultContext());

        $products = [];
        foreach ($demoProductsResult->getEntities() as $product) {
            $products[] = [
                'productNumber' => $product->getProductNumber(),
                'name'          => $product->getName(),
                'ean'           => $product->getEan(),
                'mpn'           => $product->getManufacturerNumber()
            ];
        }

        return new JsonResponse([
            'count'    => $demoProductsResult->getTotal(),
            'products' => $products
        ]);
    }

    /**
     * Purges all imported demo products.
     */
    #[Route(
        path: '/api/topdata-demo-data/remove-demodata',
        name: 'api.topdata_demo_data.remove_demodata',
        methods: ['POST']
    )]
    public function removeDemoData(): JsonResponse
    {
        $context = Context::createDefaultContext();
        $demoProducts = $this->productService->getDemoProducts($context)->getEntities();

        $deletedProductsData = [];
        foreach ($demoProducts as $product) {
            $deletedProductsData[] = [
                'productNumber' => $product->getProductNumber(),
                'name'          => $product->getName(),
                'ean'           => $product->getEan(),
                'mpn'           => $product->getManufacturerNumber()
            ];
        }

        $this->productService->removeDemoProducts($context);

        return new JsonResponse([
            'status'          => 'success',
            'deletedCount'    => count($deletedProductsData),
            'deletedProducts' => $deletedProductsData
        ]);
    }
}
```

#### Task 3.2: Refactor `TopdataDemoDataImporterSW6.php`
**File to Edit:** `src/TopdataDemoDataImporterSW6.php` [MODIFY]
- Align class and method docblocks. No changes to base logic.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductService;

/**
 * Plugin lifecycle class responsible for managing database schemas and settings.
 */
class TopdataDemoDataImporterSW6 extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME        = 'topdata_demo_data_importer';
    public const CUSTOM_FIELD_IS_DEMO_PRODUCT = 'topdata_demo_data_importer_is_demo_product';

    /**
     * Execution routine during plugin installation.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->_createCustomFieldSet($installContext->getContext());
    }

    /**
     * Execution routine during plugin uninstallation.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $context = $uninstallContext->getContext();
        $this->container->get(DemoProductService::class)->removeDemoProducts($context);
        $this->_removeCustomFieldSet($context);
    }

    /**
     * Declares custom field structures for product models.
     */
    private function _createCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $exists = $customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0;

        if ($exists) {
            return;
        }

        $customFieldSetRepository->upsert([
            [
                'name'         => self::CUSTOM_FIELD_SET_NAME,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Topdata Demo Data Importer',
                        'de-DE' => 'Topdata Demo Data Importer',
                    ],
                ],
                'relations'    => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name'   => self::CUSTOM_FIELD_IS_DEMO_PRODUCT,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label'               => [
                                'en-GB' => 'Is a demo product',
                                'de-DE' => 'Ist ein Demo-Produkt',
                            ],
                            'componentName'       => 'sw-field',
                            'customFieldType'     => 'checkbox',
                            'customFieldPosition' => 1,
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    /**
     * Deletes the custom fields from custom_field records.
     */
    private function _removeCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $id = $customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            return;
        }

        $customFieldSetRepository->delete([['id' => $id]], $context);
    }
}
```

---

### Phase 4: Final Validation and Code Analysis
1. Ensure there are no static code warnings, and verify type-strictness.
2. Execute analysis:
   ```bash
   composer phpstan
   ```
3. Run Rector rules to ensure that PHP 8.2 compatible structures are fully active:
   ```bash
   vendor/bin/rector process src
   ```

---

### Phase 5: Implementation Reporting
At the conclusion of the refactoring process, generate an implementation report.

**File to Create:** `_ai/backlog/reports/260310_1000__IMPLEMENTATION_REPORT__refactor_plugin.md` [NEW FILE]

```markdown
---
filename: "_ai/backlog/reports/260310_1000__IMPLEMENTATION_REPORT__refactor_plugin.md"
title: "Report: Refactor Demo Data Importer Plugin to Clean Architecture and Modern Standards"
createdAt: 2026-03-10 12:00
updatedAt: 2026-03-10 12:00
planFile: "_ai/backlog/active/260310_1000__IMPLEMENTATION_PLAN__refactor_plugin.md"
project: "SW6.7 Plugin"
status: completed
filesCreated: 1
filesModified: 9
filesDeleted: 0
tags: [refactoring, shopware, commands, php8, code-quality]
documentType: IMPLEMENTATION_REPORT
---

# Implementation Report: Refactor Plugin Codebase

## Summary
The plugin codebase was refactored to bring all commands, services, DTOs, and controllers into compliance with PHP 8.2 and modern Shopware 6.7 plugin standards. Direct system out blocks and redundant annotations were resolved.

## Files Changed
### New Files
- `_ai/backlog/reports/260310_1000__IMPLEMENTATION_REPORT__refactor_plugin.md` (This report file)

### Modified Files
- `src/Command/ImportDemoProductsCommand.php` - Unified visual feedback through `CliLogger` and renamed private methods.
- `src/Command/ImportProductsCsvCommand.php` - Replaced legacy outputs with the unified `CliLogger` service wrapper.
- `src/Command/RemoveDemoProductsCommand.php` - Migrated `$this->cliStyle` methods to `CliLogger`.
- `src/Command/UseWebserviceDemoCredentialsCommand.php` - Updated command logging layout.
- `src/Controller/TopdataDemoDataAdminApiController.php` - Upgraded method comments.
- `src/DTO/CsvConfiguration.php` - Simplified field properties and comments.
- `src/Service/DemoDataImportService.php` - Cleaned parameter descriptions.
- `src/Service/DemoProductService.php` - Audited parameters and class comments.
- `src/Service/ProductCsvReader.php` - Standardized helper naming configurations (`_processFile` and `_mapRowToProduct`).

## Key Changes
- **CliLogger Standardization:** Replaced raw `echo` outputs with uniform, colored CLI messages from `CliLogger`.
- **Private Method Conventions:** Prefixed all internal private helper methods with a leading underscore (`_`).
- **Clean Docblocks:** Excised redundant annotations and unified formatting structure.

## Technical Decisions
- Preserved existing database migration schemas to prevent side-effects on production databases.
- Retained absolute compatibility across Shopware versions 6.5, 6.6, and 6.7.
```

