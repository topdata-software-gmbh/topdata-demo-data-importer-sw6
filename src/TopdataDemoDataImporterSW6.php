<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoProductServiceInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\Setup\CustomFieldInstaller;

class TopdataDemoDataImporterSW6 extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME        = 'topdata_demo_data_importer';
    public const CUSTOM_FIELD_IS_DEMO_PRODUCT = 'topdata_demo_data_importer_is_demo_product';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->_getInstaller()->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $context = $uninstallContext->getContext();
        $this->container->get(DemoProductServiceInterface::class)->removeDemoProducts($context);
        $this->_getInstaller()->uninstall($context);
    }

    private function _getInstaller(): CustomFieldInstaller
    {
        return new CustomFieldInstaller(
            $this->container->get('custom_field_set.repository')
        );
    }
}
