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
     * @return array<string, ProductImportDto>
     */
    public function parseProductsFromCsv(string $filePath, CsvConfiguration $config): array
    {
        return $this->productCsvReader->readProducts($filePath, $config);
    }

    /**
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

    private function _getTaxId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', 19.0));
        $criteria->setLimit(1);

        $tax = $this->taxRepository->search($criteria, $this->context)->first();

        if ($tax === null) {
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->setLimit(1);
            $tax = $this->taxRepository->search($fallbackCriteria, $this->context)->first();
        }

        if ($tax === null) {
            throw new \RuntimeException('No tax settings configured in this shop. Please verify core tax classes are installed.');
        }

        return $tax->getId();
    }

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
