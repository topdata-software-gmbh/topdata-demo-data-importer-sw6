<?php declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Plugin to import demo data for Topdata products into Shopware 6.
 *
 * This plugin installs a custom field set to mark products as demo products.
 */
class TopdataDemoDataImporterSW6 extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME        = 'topdata_demo_data_importer';
    public const CUSTOM_FIELD_IS_DEMO_PRODUCT = 'topdata_demo_data_importer_is_demo_product';

    /**
     * Installs the plugin.
     *
     * This method is called during the plugin installation process.
     * It creates a custom field set to mark products as demo products.
     *
     * @param InstallContext $installContext The context of the installation process.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->_createCustomFieldSet($installContext->getContext());
    }

    /**
     * Uninstalls the plugin.
     *
     * This method is called during the plugin uninstallation process.
     * It removes the custom field set, unless the user has chosen to keep the user data.
     *
     * @param UninstallContext $uninstallContext The context of the uninstallation process.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->_removeCustomFieldSet($uninstallContext->getContext());
    }

    /**
     * Creates the custom field set for marking demo products.
     *
     * This custom field set includes a boolean custom field to indicate whether a product is a demo product.
     *
     * @param Context $context The context of the current operation.
     */
    private function _createCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        // ---- Check if the custom field set already exists
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $exists = $customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0;

        if ($exists) {
            return;
        }

        // ---- Create the custom field set
        $customFieldSetRepository->upsert([
            [
                'name'         => self::CUSTOM_FIELD_SET_NAME,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Topdata Demo Data Importer',
                        'de-DE' => 'Topdata Demo Data Importer',
                    ],
                ],
                'relations'    => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name'   => self::CUSTOM_FIELD_IS_DEMO_PRODUCT,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label'               => [
                                'en-GB' => 'Is a demo product',
                                'de-DE' => 'Ist ein Demo-Produkt',
                            ],
                            'componentName'       => 'sw-field',
                            'customFieldType'     => 'checkbox',
                            'customFieldPosition' => 1,
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    /**
     * Removes the custom field set.
     *
     * @param Context $context The context of the current operation.
     */
    private function _removeCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        // ---- Check if the custom field set exists
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $id = $customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            return;
        }

        // ---- Delete the custom field set
        $customFieldSetRepository->delete([['id' => $id]], $context);
    }
}