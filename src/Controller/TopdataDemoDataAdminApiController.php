<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;

/**
 * 11/2024 extracted from TopdataWebserviceConnectorAdminApiController
 */
#[Route(
    defaults: ['_routeScope' => ['administration']],
)]
class TopdataDemoDataAdminApiController extends AbstractController
{

    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
        private readonly EntityRepository      $productRepository,
    )
    {
    }


    /**
     * Install demo data.
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
     * Remove demo data.
     */
    #[Route(
        path: '/api/topdata-demo-data/remove-demodata',
        name: 'api.topdata_demo_data.remove_demodata',
        methods: ['POST']
    )]
    public function removeDemoData(): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.topdata_demo_data_importer_is_demo_product', true));
        $demoProducts = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $deletedProductsData = [];
        $demoProductIds = [];

        foreach ($demoProducts as $product) {
            $deletedProductsData[] = [
                'productNumber' => $product->getProductNumber(),
                'name' => $product->getName(),
                'ean' => $product->getEan(),
                'mpn' => $product->getManufacturerNumber()
            ];
            $demoProductIds[] = $product->getId();
        }

        if (!empty($demoProductIds)) {
            $this->productRepository->delete(array_map(fn($id) => ['id' => $id], $demoProductIds), Context::createDefaultContext());
        }

        return new JsonResponse([
            'status' => 'success',
            'deletedCount' => count($demoProductIds),
            'deletedProducts' => $deletedProductsData
        ]);
    }
}
