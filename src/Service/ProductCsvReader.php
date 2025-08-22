<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use RuntimeException;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

/**
 * Service for reading product data from CSV files.
 * This class handles file reading, parsing, and mapping CSV data to a product array.
 * 07/2024 created
 */
class ProductCsvReader
{
    /**
     * Reads product data from a CSV file based on the provided configuration.
     *
     * @param string $filePath The path to the CSV file.
     * @param CsvConfiguration $config The configuration for reading the CSV file.
     *
     * @return array An array of products, where the key is the product number.
     *
     * @throws RuntimeException if the file cannot be read or is invalid.
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
            return $this->processFile($handle, $config);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Processes the CSV file handle and extracts product data.
     *
     * @param resource $handle The file handle for the CSV file.
     * @param CsvConfiguration $config The configuration for reading the CSV file.
     *
     * @return array An array of products, where the key is the product number.
     *
     * @throws RuntimeException if required columns are missing.
     */
    private function processFile($handle, CsvConfiguration $config): array
    {
        $products = [];
        $lineNumber = 0;
        $mapping = $config->getColumnMapping();

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            // ---- Skip lines before the start line
            if ($lineNumber < $config->getStartLine()) {
                continue;
            }

            // ---- Break if the end line is reached
            if ($config->getEndLine() !== null && $lineNumber > $config->getEndLine()) {
                break;
            }

            // ---- Extract values from the line
            $values = array_map(
                fn($val) => trim($val, $config->getEnclosure()),
                explode($config->getDelimiter(), $line)
            );

            // ---- Skip lines without required columns
            if (!isset($values[$mapping['number']]) || !isset($values[$mapping['name']])) {
                continue;
            }

            $products[$values[$mapping['number']]] = $this->mapRowToProduct($values, $mapping);
        }

        return $products;
    }

    /**
     * Maps a single row of CSV data to a product array.
     *
     * @param array $values The array of values from the CSV row.
     * @param array $mapping The mapping of CSV columns to product fields.
     *
     * @return array An array representing a single product.
     */
    private function mapRowToProduct(array $values, array $mapping): array
    {
        $product = [
            'productNumber' => $values[$mapping['number']],
            'name'          => $values[$mapping['name']],
        ];

        // ---- Optional fields
        $optionalFields = [
            'wsid'        => 'topDataId',
            'description' => 'description',
            'ean'         => 'ean',
            'mpn'         => 'mpn',
            'brand'       => 'brand'
        ];

        // ---- Map optional fields if they exist
        foreach ($optionalFields as $csvField => $productField) {
            if (isset($mapping[$csvField]) && isset($values[$mapping[$csvField]])) {
                $product[$productField] = $values[$mapping[$csvField]];
            }
        }

        return $product;
    }
}