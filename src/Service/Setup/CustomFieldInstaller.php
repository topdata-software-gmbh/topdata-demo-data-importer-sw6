<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service\Setup;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;

class CustomFieldInstaller
{
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository
    ) {
    }

    public function install(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME));
        $exists = $this->customFieldSetRepository->searchIds($criteria, $context)->getTotal() > 0;

        if ($exists) {
            return;
        }

        $this->customFieldSetRepository->upsert([
            [
                'name'         => TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME,
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
                        'name'   => TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT,
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

    public function uninstall(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', TopdataDemoDataImporterSW6::CUSTOM_FIELD_SET_NAME));
        $id = $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            return;
        }

        $this->customFieldSetRepository->delete([['id' => $id]], $context);
    }
}
