<?php

declare(strict_types=1);

namespace Topdata\TopdataConnectorSW6\Controller\Admin;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Topdata\TopdataDemoDataImportSW6\Service\DemoDataImportService;

/**
 * 11/2024 extracted from TopdataWebserviceConnectorAdminApiController
 */
#[Route(defaults: ['_routeScope' => ['administration']])]
class TopdataDemoDataAdminApiController extends AbstractController
{

    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
    )
    {
    }


    /**
     * Install demo data.
     */
    #[Route(
        path: '/api/topdata/connector-install-demodata',
        name: 'api.action.topdata.connector-install-demodata',
        methods: ['GET']
    )]
    public function installDemoData(Request $request, Context $context): JsonResponse
    {
        $result = $this->demoDataImportService->installDemoData();

        return new JsonResponse($result);
    }
}
