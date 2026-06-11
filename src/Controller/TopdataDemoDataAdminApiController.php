<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportServiceInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface;

/**
 * Administration API controller providing endpoints for demo data operations.
 */
#[Route(
    defaults: ['_routeScope' => ['administration']],
)]
class TopdataDemoDataAdminApiController extends AbstractController
{
    public function __construct(
        private readonly DemoDataImportServiceInterface $demoDataImportService,
        private readonly DemoProductServiceInterface     $productService,
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
