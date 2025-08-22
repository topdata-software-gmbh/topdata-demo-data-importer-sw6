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
use Shopware\Core\Framework\Uuid\Uuid;
use Topdata\TopdataDemoDataImporterSW6\DTO\CsvConfiguration;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;
use Topdata\TopdataFoundationSW6\Service\LocaleHelperService;

/**
 * Service class for handling product-related operations, such as importing, creating, and clearing products.
 * 07/2024 created (extracted from "ProductsCommand")
 * 11/2024 moved from TopdataConnectorSW6 to TopdataDemoDataImporterSW6
 */
class ProductService
{
    private string $systemDefaultLocaleCode;
    private Context $context;


    public function __construct(
        private readonly EntityRepository    $productRepository,
        private readonly EntityRepository    $productManufacturerRepository,
        private readonly Connection          $connection,
        private readonly ProductCsvReader    $productCsvReader,
        private readonly LocaleHelperService $localeHelperService,
    )
    {
        $this->context = Context::createDefaultContext();
        $this->systemDefaultLocaleCode = $this->localeHelperService->getLocaleCodeOfSystemLanguage();
    }


    /**
     * Retrieves the ID of a tax with a rate of 19.00 or the first available tax ID.
     *
     * @return string The tax ID.
     *
     * @throws \RuntimeException If no tax is found.
     */
    private function getTaxId(): string
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
     * Retrieves the ID of the storefront sales channel.
     *
     * @return string The storefront sales channel ID.
     *
     * @throws \RuntimeException If no sales channel is found.
     */
    private function getStorefrontSalesChannel(): string
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

    /**
     * Parses product data from a CSV file.
     *
     * @param string $filePath The path to the CSV file.
     * @param CsvConfiguration $config The CSV configuration.
     *
     * @return array The parsed product data.
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array
    {
        return $this->productCsvReader->readProducts($filePath, $config);
    }

    /**
     * Forms an array of product data suitable for creating products in Shopware 6.
     *
     * @param array $input An array of product data from the CSV file.
     * @param float $price The base price of the product.
     * @param string|null $categoryId The ID of the category to assign the product to.
     *
     * @return array The formatted product data.
     */
    public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array
    {
        $output = [];
        $taxId = $this->getTaxId();
        $storefrontSalesChannel = $this->getStorefrontSalesChannel();
        $priceTax = $price * (1.19);

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

//            if (isset($in['brand'])) {
//                $prod['manufacturer'] = [
//                    'id' => $this->_getManufacturerIdByName($in['brand']),
//                ];
//            }

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
     * Creates products in Shopware 6.
     *
     * @param array $products An array of product data.
     */
    public function createProducts(array $products): void
    {
        $this->productRepository->create($products, $this->context);
    }

    /**
     * Clears existing products based on their product number.
     *
     * @param array $products An array of product data, indexed by product number.
     *
     * @return array The product data with existing products removed.
     */
    public function clearExistingProductsByProductNumber(array $products): array
    {
        $rezProducts = $products;
        // ---- Chunk the products array to avoid exceeding database limits
        $product_arrays = array_chunk($products, 50, true);
        foreach ($product_arrays as $prods) {
            // ---- Create a criteria to search for products with the given product numbers
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('productNumber', array_keys($prods)));
            $foundedProducts = $this->productRepository->search($criteria, $this->context)->getEntities();
            // ---- Unset the products that were found in the database
            foreach ($foundedProducts as $foundedProd) {
                unset($rezProducts[$foundedProd->getProductNumber()]);
            }
        }

        return $rezProducts;
    }

//    private function _getManufacturerIdByName(string $name): string
//    {
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('name', $name));
//
//        $manufacturer = $this->productManufacturerRepository->search($criteria, $this->context)->first();
//
//        if ($manufacturer !== null) {
//            return $manufacturer->getId();
//        }
//
//        // Manufacturer not found, create a new one
//        $newManufacturerId = Uuid::randomHex();
//        $this->productManufacturerRepository->create([
//            [
//                'id'   => $newManufacturerId,
//                'name' => $name,
//            ]
//        ], $this->context);
//
//        return $newManufacturerId;
//    }


}