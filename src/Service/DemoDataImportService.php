<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;

class DemoDataImportService implements DemoDataImportServiceInterface
{
    public function __construct(
        private readonly DemoProductServiceInterface $productService,
        private readonly ProductCsvReaderInterface  $csvReader
    ) {
    }

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
