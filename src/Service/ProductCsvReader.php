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
