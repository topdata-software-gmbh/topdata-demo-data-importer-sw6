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

class TopdataDemoDataImporterSW6 extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME        = 'topdata_demo_data_importer';
    public const CUSTOM_FIELD_IS_DEMO_PRODUCT = 'topdata_demo_data_importer_is_demo_product';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->_createCustomFieldSet($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->_removeCustomFieldSet($uninstallContext->getContext());
    }

    private function _createCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $exists = $customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0;

        if ($exists) {
            return;
        }

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

    private function _removeCustomFieldSet(Context $context): void
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));
        $id = $customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            return;
        }

        $customFieldSetRepository->delete([['id' => $id]], $context);
    }
}
