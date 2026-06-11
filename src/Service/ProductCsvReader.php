<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use RuntimeException;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\DTO\ProductImportDto;

class ProductCsvReader implements ProductCsvReaderInterface
{
    /**
     * @return array<string, ProductImportDto>
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
