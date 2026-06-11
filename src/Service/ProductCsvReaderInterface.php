<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

interface ProductCsvReaderInterface
{
    /**
     * @return array<string, \Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto>
     */
    public function readProducts(string $filePath, CsvConfiguration $config): array;
}
