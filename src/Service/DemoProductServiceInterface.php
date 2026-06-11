<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

interface DemoProductServiceInterface
{
    /**
     * @return array<string, \Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto>
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array;

    public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array;

    public function createProducts(array $products): void;

    public function getDemoProducts(Context $context): EntitySearchResult;

    public function removeDemoProducts(Context $context): void;

    /**
     * @param array<string, mixed> $products
     */
    public function clearExistingProductsByProductNumber(array $products): array;
}
