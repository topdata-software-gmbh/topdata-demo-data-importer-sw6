---
filename: "_ai/backlog/active/260310_1300__IMPLEMENTATION_PLAN__modern_architecture_sw67.md"
title: "Refactor Demo Data Importer to Modern Interface-Driven Architecture for Shopware 6.7"
createdAt: 2026-03-10 13:00
updatedAt: 2026-03-10 13:00
status: completed
completedAt: 2026-06-11 13:07
priority: high
tags: [refactoring, shopware6.7, solid, type-safety, clean-architecture]
estimatedComplexity: complex
documentType: IMPLEMENTATION_PLAN
---

## Architectural Weaknesses in the Current Codebase
While the previous cosmetic refactoring satisfied basic stylistic guidelines, several structural problems remain under the hood:
1. **Duplicate CSV Parsing Logic:** `DemoDataImportService` manually opens, reads, and splits CSV lines with custom index searching, completely ignoring the generic `ProductCsvReader` utility.
2. **Untyped Associative Arrays:** Data passes between commands, parsing services, formatting services, and controllers as unstructured PHP arrays, removing IDE auto-completion and static analysis verification.
3. **Infrastructure Coupling in Core Domain:** `DemoProductService` runs raw SQL queries against DBAL connections to resolve tax rate mappings and storefront channel UUIDs, bypassing the Shopware Data Abstraction Layer (DAL).
4. **Command Class Bloat:** Commands like `ImportDemoProductsCommand` contain complex domain concerns, such as traversing category entities, building breadcrumbs, and sorting trees for interactive choices.
5. **Cluttered Plugin Lifecycle:** The main plugin class `TopdataDemoDataImporterSW6` directly executes entity repository operations to create and destroy custom field definitions.

---

## Executive Summary of the Solution
We will reconstruct the plugin's service layer to focus on type safety, SOLID principles, and clean separation of concerns for Shopware 6.7:
- **Type-Safe Domain Transfer Objects:** Introduce `ProductImportDto` to represent the parsed entities.
- **Service Decoupling & Interfaces:** Program against interfaces for all services.
- **Unification of Parser Logic:** Repurpose `DemoDataImportService` to delegate parsing to `ProductCsvReaderInterface`, removing duplication.
- **Pure DAL Integration:** Replace direct database connections in `DemoProductService` with Shopware's native `tax.repository` and `sales_channel.repository` DAL calls.
- **Domain Services Extraction:**
  - Create a dedicated `CategorySelectorService` to handle nested category hierarchy tree resolutions for interactive CLI environments.
  - Create a `CustomFieldInstaller` setup helper to cleanly isolate plugin installation and uninstallation tasks away from the core container class.

---

## Project Environment
- **Project Name:** SW6.7 Plugin (Topdata Demo Data Importer)
- **Backend Root:** `src`
- **PHP Version:** `~8.2.0 || ~8.3.0 || ~8.4.0`
- **Target Platform:** Shopware 6.7 (Exclusive, dropping backward compatibility layers)

---

## Multi-Phased Implementation Plan

### Phase 1: Creating Type-Safe DTOs & Interfaces
We establish domain-wide contracts and encapsulate parsed product characteristics inside immutable PHP 8 readonly classes.

#### Task 1.1: Create `ProductImportDto`
**File to Create:** `src/DTO/ProductImportDto.php` [NEW FILE]

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\DTO;

/**
 * Immutable Data Transfer Object representing type-safe product structures.
 */
class ProductImportDto
{
    public function __construct(
        private readonly string $productNumber,
        private readonly string $name,
        private readonly ?string $ean = null,
        private readonly ?string $mpn = null,
        private readonly ?string $description = null,
        private readonly ?string $topDataId = null,
        private readonly ?string $brand = null
    ) {
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function getMpn(): ?string
    {
        return $this->mpn;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTopDataId(): ?string
    {
        return $this->topDataId;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }
}
```

#### Task 1.2: Define Service Interfaces
We define high-level contracts for database readers, product imports, and category processing.

**File to Create:** `src/Service/ProductCsvReaderInterface.php` [NEW FILE]
```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

/**
 * Contract for parsing a CSV file into type-safe ProductImportDto objects.
 */
interface ProductCsvReaderInterface
{
    /**
     * Parses the given file and maps it to a list of DTO objects.
     *
     * @return array<string, \Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto>
     */
    public function readProducts(string $filePath, CsvConfiguration $config): array;
}
```

**File to Create:** `src/Service/DemoProductServiceInterface.php` [NEW FILE]
```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

/**
 * Contract for executing database write and purge routines for demo products.
 */
interface DemoProductServiceInterface
{
    /**
     * Parses products from a custom CSV file.
     *
     * @return array<string, \Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto>
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array;

    /**
     * Converts type-safe DTO items into Shopware-compatible product creation payloads.
     */
    public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array;

    /**
     * Saves raw arrays to the Shopware core database.
     */
    public function createProducts(array $products): void;

    /**
     * Retrieves all products flagged with the demo custom field.
     */
    public function getDemoProducts(Context $context): EntitySearchResult;

    /**
     * Purges all demo products.
     */
    public function removeDemoProducts(Context $context): void;

    /**
     * filters out products that already exist in the database.
     *
     * @param array<string, mixed> $products
     */
    public function clearExistingProductsByProductNumber(array $products): array;
}
```

**File to Create:** `src/Service/DemoDataImportServiceInterface.php` [NEW FILE]
```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

/**
 * Contract for importing preset demo datasets.
 */
interface DemoDataImportServiceInterface
{
    /**
     * Imports standard demo data into a specific category.
     */
    public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array;
}
```

**File to Create:** `src/Service/CategorySelectorServiceInterface.php` [NEW FILE]
```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

/**
 * Contract for fetching and resolving nested category hierarchies for the console interface.
 */
interface CategorySelectorServiceInterface
{
    /**
     * Fetches categories and resolves their parents to construct display names.
     *
     * @return array<string, string> Key: category ID, Value: formatted breadcrumb name.
     */
    public function getCategoryChoices(): array;

    /**
     * Resolves the string name of a category by its ID.
     */
    public function getCategoryName(string $categoryId): ?string;
}
```

---

### Phase 2: Consolidating CSV Parsing & Reader Services
We implement `ProductCsvReaderInterface` to output `ProductImportDto` entities, then simplify the duplicate reading logic inside `DemoDataImportService`.

#### Task 2.1: Implement Modernised `ProductCsvReader`
**File to Edit:** `src/Service/ProductCsvReader.php` [MODIFY]
- Implement the interface and return typed `ProductImportDto` elements.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use RuntimeException;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto;

/**
 * Service to process physical file layouts into immutable ProductImportDto models.
 */
class ProductCsvReader implements ProductCsvReaderInterface
{
    /**
     * Reads custom CSV streams and returns typed collection arrays.
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
     * Processes lines and constructs DTO payloads.
     *
     * @return array<string, ProductImportDto>
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

            $number = $values[$mapping['number']];
            $products[$number] = $this->_mapRowToProduct($values, $mapping);
        }

        return $products;
    }

    /**
     * Converts string arrays into a validated ProductImportDto.
     */
    private function _mapRowToProduct(array $values, array $mapping): ProductImportDto
    {
        return new ProductImportDto(
            productNumber: $values[$mapping['number']],
            name: $values[$mapping['name']],
            ean: (isset($mapping['ean']) && isset($values[$mapping['ean']])) ? $values[$mapping['ean']] : null,
            mpn: (isset($mapping['mpn']) && isset($values[$mapping['mpn']])) ? $values[$mapping['mpn']] : null,
            description: (isset($mapping['description']) && isset($values[$mapping['description']])) ? $values[$mapping['description']] : null,
            topDataId: (isset($mapping['wsid']) && isset($values[$mapping['wsid']])) ? $values[$mapping['wsid']] : null,
            brand: (isset($mapping['brand']) && isset($values[$mapping['brand']])) ? $values[$mapping['brand']] : null
        );
    }
}
```

#### Task 2.2: Refactor `DemoDataImportService` to Eliminate Duplication
**File to Edit:** `src/Service/DemoDataImportService.php` [MODIFY]
- Inject `ProductCsvReaderInterface`.
- Read and locate header files, construct a standard `CsvConfiguration` dynamic map, and use `ProductCsvReader` to read the data.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

/**
 * Service managing preset demo products, delegating parsing to the CSV reader service.
 */
class DemoDataImportService implements DemoDataImportServiceInterface
{
    public function __construct(
        private readonly DemoProductServiceInterface $productService,
        private readonly ProductCsvReaderInterface  $csvReader
    ) {
    }

    /**
     * Automatically reads columns from the first line and parses records through ProductCsvReader.
     */
    public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array
    {
        if (!$filename) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo data not found!',
            ];
        }

        $file = __DIR__ . '/../Resources/demo-data/' . $filename;
        if (!file_exists($file)) {
            return [
                'success'        => false,
                'additionalInfo' => 'file with demo not accessible!',
            ];
        }

        $columnMapping = $this->_resolveHeaders($file);
        if ($columnMapping === null) {
            return [
                'success'        => false,
                'additionalInfo' => 'Required columns are missing or file is unreadable!',
            ];
        }

        // Configuration starts at line 2 to skip headers
        $config = new CsvConfiguration(';', '"', 2, null, $columnMapping);
        
        try {
            $parsedProducts = $this->csvReader->readProducts($file, $config);
        } catch (\Exception $e) {
            return [
                'success'        => false,
                'additionalInfo' => $e->getMessage(),
            ];
        }

        $products = $this->productService->clearExistingProductsByProductNumber($parsedProducts);
        
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

    /**
     * Reads header rows to map CSV columns to expected entity mappings.
     */
    private function _resolveHeaders(string $file): ?array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return null;
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false) {
            return null;
        }

        $headers = explode(';', $line);
        $mapping = [];

        foreach ($headers as $key => $header) {
            $header = trim($header);
            if ($header === 'article_no') {
                $mapping['number'] = $key;
            }
            if ($header === 'short_desc') {
                $mapping['name'] = $key;
            }
            if ($header === 'ean') {
                $mapping['ean'] = $key;
            }
            if ($header === 'oem') {
                $mapping['mpn'] = $key;
            }
        }

        if (!isset($mapping['number']) || !isset($mapping['name'])) {
            return null;
        }

        return $mapping;
    }
}
```

---

### Phase 3: Decoupling Database Infrastructure from Core Services
We remove direct DBAL SQL execution statements. `DemoProductService` is modified to use Shopware's native `tax` and `sales_channel` repositories. We also update payloads to use DTO entities.

#### Task 3.1: Upgrade `DemoProductService`
**File to Edit:** `src/Service/DemoProductService.php` [MODIFY]
- Inject `tax.repository` and `sales_channel.repository`.
- Replace physical SQL lookups with clean Shopware DAL searches.
- Refactor helper parameters to accept type-safe `ProductImportDto` models.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;
use Topdata\TopdataFoundationSW6\Service\LocaleHelperService;

/**
 * Service implementing product manipulation layer strictly with Shopware repositories.
 */
class DemoProductService implements DemoProductServiceInterface
{
    private string $systemDefaultLocaleCode;
    private Context $context;

    public function __construct(
        private readonly EntityRepository    $productRepository,
        private readonly EntityRepository    $taxRepository,
        private readonly EntityRepository    $salesChannelRepository,
        private readonly ProductCsvReaderInterface $productCsvReader,
        private readonly LocaleHelperService $localeHelperService,
    ) {
        $this->context = Context::createDefaultContext();
        $this->systemDefaultLocaleCode = $this->localeHelperService->getLocaleCodeOfSystemLanguage();
    }

    /**
     * Resolves product arrays.
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array
    {
        return $this->productCsvReader->readProducts($filePath, $config);
    }

    /**
     * Creates clean Shopware schema entities from ProductImportDto parameters.
     *
     * @param array<string, ProductImportDto> $input
     */
    public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array
    {
        $output = [];
        $taxId = $this->_getTaxId();
        $storefrontSalesChannel = $this->_getStorefrontSalesChannel();
        $priceTax = $price * 1.19;

        foreach ($input as $dto) {
            /** @var ProductImportDto $dto */
            $prod = [
                'id'               => Uuid::randomHex(),
                'productNumber'    => $dto->getProductNumber(),
                'active'           => true,
                'taxId'            => $taxId,
                'stock'            => 10,
                'shippingFree'     => false,
                'purchasePrice'    => $priceTax,
                'displayInListing' => true,
                'name'             => [
                    $this->systemDefaultLocaleCode => $dto->getName(),
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

            if ($dto->getDescription() !== null) {
                $prod['description'] = [
                    $this->systemDefaultLocaleCode => $dto->getDescription(),
                ];
            }

            if ($dto->getMpn() !== null) {
                $prod['manufacturerNumber'] = $dto->getMpn();
            }

            if ($dto->getEan() !== null) {
                $prod['ean'] = $dto->getEan();
            }

            if ($dto->getTopDataId() !== null) {
                $prod['topdata'] = [
                    'topDataId' => $dto->getTopDataId(),
                ];
            }

            $output[] = $prod;
        }

        return $output;
    }

    public function createProducts(array $products): void
    {
        $this->productRepository->create($products, $this->context);
    }

    public function getDemoProducts(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT, true));
        $criteria->setLimit(500);

        return $this->productRepository->search($criteria, $context);
    }

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
     * Filters out product records already stored in the system database.
     *
     * @param array<string, ProductImportDto> $products
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
     * Resolves tax ID using native DAL criteria checks.
     */
    private function _getTaxId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', 19.0));
        $criteria->setLimit(1);

        $tax = $this->taxRepository->search($criteria, $this->context)->first();

        if ($tax === null) {
            // Fallback to first available tax entity if standard rate is not configured
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->setLimit(1);
            $tax = $this->taxRepository->search($fallbackCriteria, $this->context)->first();
        }

        if ($tax === null) {
            throw new \RuntimeException('No tax settings configured in this shop. Please verify core tax classes are installed.');
        }

        return $tax->getId();
    }

    /**
     * Resolves storefront ID through the DAL sales channel criteria checks.
     */
    private function _getStorefrontSalesChannel(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(1);

        $salesChannel = $this->salesChannelRepository->search($criteria, $this->context)->first();

        if ($salesChannel === null) {
            throw new \RuntimeException('No storefront sales channel was found in this Shopware installation.');
        }

        return $salesChannel->getId();
    }
}
```

---

### Phase 4: Decoupling CLI Concerns
We move visual tree manipulations and breadcrumb calculations away from `ImportDemoProductsCommand` into a isolated domain helper: `CategorySelectorService`.

#### Task 4.1: Implement `CategorySelectorService`
**File to Create:** `src/Service/CategorySelectorService.php` [NEW FILE]

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Service constructing nested category visual structures for the CLI command choice dialogs.
 */
class CategorySelectorService implements CategorySelectorServiceInterface
{
    public function __construct(
        private readonly EntityRepository $categoryRepository
    ) {
    }

    /**
     * Resolves physical names into ordered choices.
     */
    public function getCategoryChoices(): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addAssociation('parent');
        $criteria->setLimit(100);

        $categories = $this->categoryRepository->search($criteria, Context::createDefaultContext());

        if ($categories->count() === 0) {
            return [];
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
                'id'          => $category->getId(),
                'depth'       => $depth,
                'displayName' => $displayName,
            ];
        }

        usort($categoriesData, function (array $a, array $b): int {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] <=> $b['depth'];
            }
            return strcmp($a['displayName'], $b['displayName']);
        });

        $choices = [];
        foreach ($categoriesData as $data) {
            $choices[$data['id']] = $data['displayName'];
        }

        return $choices;
    }

    /**
     * Translates a category UUID to its readable title.
     */
    public function getCategoryName(string $categoryId): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();

        return $category instanceof CategoryEntity ? $category->getName() : null;
    }

    /**
     * Navigates recursively up the category tree to compile full parent-child titles.
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

#### Task 4.2: Refactor `ImportDemoProductsCommand`
**File to Edit:** `src/Command/ImportDemoProductsCommand.php` [MODIFY]
- Inject `CategorySelectorServiceInterface` and remove direct visual logic.

```php
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

/**
 * Command allowing custom categories or auto-prompt choice dialogs when importing products.
 */
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
```

---

### Phase 5: Delegating Installation Lifecycles
We isolate Custom Field configuration routines into a separate setup utility class, making the core plugin class clean and simple.

#### Task 5.1: Create `CustomFieldInstaller`
**File to Create:** `src/Service/Setup/CustomFieldInstaller.php` [NEW FILE]

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service\Setup;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;

/**
 * Handles custom database and setup field installations during plugin lifecycle changes.
 */
class CustomFieldInstaller
{
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository
    ) {
    }

    /**
     * Registers schema changes to define custom product tags.
     */
    public function install(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME));
        $exists = $this->customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0;

        if ($exists) {
            return;
        }

        $this->customFieldSetRepository->upsert([
            [
                'name'         => TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME,
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
                        'name'   => TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT,
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
     * Deletes custom field installations.
     */
    public function uninstall(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME));
        $id = $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            return;
        }

        $this->customFieldSetRepository->delete([['id' => $id]], $context);
    }
}
```

#### Task 5.2: Simplify `TopdataDemoDataImporterSW6.php`
**File to Edit:** `src/TopdataDemoDataImporterSW6.php` [MODIFY]
- Retrieve `CustomFieldInstaller` from the container and delegate installation/uninstallation routines.

```php
<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\Setup\CustomFieldInstaller;

/**
 * Main plugin class. Delegates setup and field lifecycles to setup helper services.
 */
class TopdataDemoDataImporterSW6 extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME        = 'topdata_demo_data_importer';
    public const CUSTOM_FIELD_IS_DEMO_PRODUCT = 'topdata_demo_data_importer_is_demo_product';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->_getInstaller()->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $context = $uninstallContext->getContext();
        $this->container->get(DemoProductServiceInterface::class)->removeDemoProducts($context);
        $this->_getInstaller()->uninstall($context);
    }

    /**
     * Instantiates the custom field setup service.
     */
    private function _getInstaller(): CustomFieldInstaller
    {
        return new CustomFieldInstaller(
            $this->container->get('custom_field_set.repository')
        );
    }
}
```

---

### Phase 6: Updating Service Configurations
We must explicitly update our service definitions to link our interfaces to the correct concrete service implementations.

#### Task 6.1: Update Container Declarations
**File to Edit:** `src/Resources/config/services.xml` [MODIFY]
- Bind interfaces to their respective services. Inject repositories cleanly for `DemoProductService` and `CategorySelectorService`.

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- CONCRETE SERVICE REFS BIND TO INTERFACES -->
        <service id="Topdata\TopdataDemoDataImporterSW6\Service\ProductCsvReaderInterface"
                 class="Topdata\TopdataDemoDataImporterSW6\Service\ProductCsvReader"
                 autowire="true"/>

        <service id="Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface"
                 class="Topdata\TopdataDemoDataImporterSW6\Service\DemoProductService"
                 autowire="true">
            <argument type="service" key="$taxRepository" id="tax.repository"/>
            <argument type="service" key="$salesChannelRepository" id="sales_channel.repository"/>
        </service>

        <service id="Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportServiceInterface"
                 class="Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService"
                 autowire="true"/>

        <service id="Topdata\TopdataDemoDataImporterSW6\Service\CategorySelectorServiceInterface"
                 class="Topdata\TopdataDemoDataImporterSW6\Service\CategorySelectorService"
                 autowire="true">
            <argument type="service" key="$categoryRepository" id="category.repository"/>
        </service>

        <!-- COMMANDS -->
        <service id="Topdata\TopdataDemoDataImporterSW6\Command\ImportProductsCsvCommand" autowire="true">
            <tag name="console.command"/>
        </service>

        <service id="Topdata\TopdataDemoDataImporterSW6\Command\ImportDemoProductsCommand" autowire="true">
            <tag name="console.command"/>
        </service>

        <service id="Topdata\TopdataDemoDataImporterSW6\Command\UseWebserviceDemoCredentialsCommand" autowire="true">
            <tag name="console.command"/>
        </service>

        <service id="Topdata\TopdataDemoDataImporterSW6\Command\RemoveDemoProductsCommand" autowire="true">
            <tag name="console.command"/>
        </service>

        <!-- CONTROLLERS -->
        <service id="Topdata\TopdataDemoDataImporterSW6\Controller\TopdataDemoDataAdminApiController" public="true" autowire="true">
            <!-- Inject interfaces rather than concrete implementations -->
            <argument type="service" id="Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportServiceInterface"/>
            <argument type="service" id="Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface"/>
            <call method="setContainer">
                <argument type="service" id="service_container"/>
            </call>
        </service>

    </services>
</container>
```

---

### Phase 7: Verification and Execution Report
Once the implementation is complete, compile the status of the refactoring into a final report.

**File to Create:** `_ai/backlog/reports/260310_1300__IMPLEMENTATION_REPORT__modern_architecture_sw67.md` [NEW FILE]

```markdown
---
filename: "_ai/backlog/reports/260310_1300__IMPLEMENTATION_REPORT__modern_architecture_sw67.md"
title: "Report: Refactor Demo Data Importer to Modern Interface-Driven Architecture for Shopware 6.7"
createdAt: 2026-03-10 14:00
updatedAt: 2026-03-10 14:00
planFile: "_ai/backlog/active/260310_1300__IMPLEMENTATION_PLAN__modern_architecture_sw67.md"
project: "SW6.7 Plugin"
status: completed
completedAt: 2026-06-11 13:07
filesCreated: 5
filesModified: 7
filesDeleted: 0
tags: [refactoring, shopware6.7, solid, type-safety, clean-architecture]
documentType: IMPLEMENTATION_REPORT
---

# Implementation Report: Modern Clean Architecture Refactor

## Summary
The codebase of the plugin was successfully refactored into a modern, interface-driven design. We removed procedural CSV parsing duplication, introduced type-safe Product DTOs, and replaced low-level direct database queries with native Shopware DAL services.

## Files Changed
### New Files
- `src/DTO/ProductImportDto.php` - Type-safe representation of imported items.
- `src/Service/ProductCsvReaderInterface.php` - Abstraction layer for CSV readers.
- `src/Service/DemoProductServiceInterface.php` - Interface for demo products.
- `src/Service/DemoDataImportServiceInterface.php` - Interface for orchestrator imports.
- `src/Service/CategorySelectorServiceInterface.php` - Interface for category helper selectors.
- `src/Service/CategorySelectorService.php` - Resolves visual category choosing lists.
- `src/Service/Setup/CustomFieldInstaller.php` - Isolates custom fields installation tasks.

### Modified Files
- `src/Service/ProductCsvReader.php` - Returns type-safe DTOs instead of raw arrays.
- `src/Service/DemoDataImportService.php` - Leverages CSV parser, removing manual parsing loops.
- `src/Service/DemoProductService.php` - Replaced raw queries with native Shopware DAL models.
- `src/Command/ImportDemoProductsCommand.php` - Simplified parsing loop and category selection.
- `src/TopdataDemoDataImporterSW6.php` - Delegated installation lifecycles to CustomFieldInstaller.
- `src/Resources/config/services.xml` - Registered the service-to-interface configurations.

## Key Changes
- **Type-Strict Arrays via DTOs:** Replaced unpredictable associative arrays with the unified `ProductImportDto` model.
- **Repository Abstraction:** Replaced low-level SQL execution queries with criteria definitions fetched from native `tax.repository` and `sales_channel.repository` structures.
- **Single Responsibility Separation:** Extracted CLI helper dependencies and installer configurations into specialized utility services.
```
