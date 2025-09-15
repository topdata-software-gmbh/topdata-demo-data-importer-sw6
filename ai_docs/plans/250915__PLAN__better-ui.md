### 1. Backend: Add an API Endpoint to Get Demo Data Status

First, we need an endpoint that the administration frontend can call to get the current number and list of demo products.

**File:** `src/Controller/TopdataDemoDataAdminApiController.php`
*   I'll add a new `getDemoDataStatus` method.
*   I will also refactor the existing `removeDemoData` method to use the custom field constant for better maintainability, just like in the new method.

```php
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
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;

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
     * Get status of demo data.
     */
    #[Route(
        path: '/api/topdata-demo-data/status',
        name: 'api.topdata_demo_data.status',
        methods: ['GET']
    )]
    public function getDemoDataStatus(): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT, true));
        $demoProductsResult = $this->productRepository->search($criteria, Context::createDefaultContext());

        $products = [];
        foreach ($demoProductsResult->getEntities() as $product) {
            $products[] = [
                'productNumber' => $product->getProductNumber(),
                'name' => $product->getName(),
                'ean' => $product->getEan(),
                'mpn' => $product->getManufacturerNumber()
            ];
        }

        return new JsonResponse([
            'count' => $demoProductsResult->getTotal(),
            'products' => $products
        ]);
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
        $criteria->addFilter(new EqualsFilter('customFields.' . TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT, true));
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
```

### 2. Frontend: Update API Service

Now, let's add a method to our frontend API service to call this new endpoint.

**File:** `src/Resources/app/administration/src/module/topdata-demo-data/service/DemoDataApiService.ts`

```typescript
import TopdataAdminApiClient from "../../../../../../../../../topdata-foundation-sw6/src/Resources/app/administration/src/service/TopdataAdminApiClient";

/**
 * Fix for "TS2304: Cannot find name Shopware"
 * TODO: check https://developer.shopware.com/docs/guides/plugins/plugins/administration/the-shopware-object.html
 */
declare var Shopware: any;

/**
 * Service for handling demo data operations in the admin interface
 */
export default class DemoDataApiService {
    private client: TopdataAdminApiClient;

    constructor() {
        this.client = Shopware.Service().get('TopdataAdminApiClient')
    }

    /**
     * Installs demo data via the API.
     * @returns {Promise} - A promise that resolves with the API response.
     */
    async installDemoData(): Promise<any> {
        const response = await this.client.post('/topdata-demo-data/install-demodata', {});
        return response.data || response;
    }

    /**
     * Gets the current status of demo data.
     * @returns {Promise<{ count: number; products: Array<any> }>} - A promise that resolves with the demo data status.
     */
    async getStatus(): Promise<{ count: number; products: Array<any> }> {
        const response = await this.client.get('/topdata-demo-data/status');
        return response.data || response;
    }

    /**
     * Removes demo data via the API.
     * @returns {Promise<{ status: string; deletedCount: number; deletedProducts: Array<any> }>} - A promise that resolves with the API response containing status, deleted count, and deleted products.
     */
    async removeDemoData(): Promise<{ status: string; deletedCount: number; deletedProducts: Array<any> }> {
        const response = await this.client.post('/topdata-demo-data/remove-demodata', {});
        return response.data || response;
    }
}
```

### 3. Frontend: Update Component Logic

Here's the core change. The component will now fetch the status when it's created and after every import or delete action.

**File:** `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/index.js`

```javascript
/**
 * Main component for the demo data import page
 * Handles the AJAX call to import demo data and displays notifications
 */

import template from './topdata-demo-data-index.html.twig';

const { Mixin } = Shopware;

Shopware.Component.register('topdata-demo-data-index', {
    template,

    inject: ['TopdataDemoDataApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            currentProducts: [],
            totalProducts: 0,
        };
    },

    created() {
        this.fetchDemoDataStatus();
    },

    computed: {
        productColumns() {
            return [
                {
                    property: 'productNumber',
                    label: this.$t('TopdataDemoDataImporterSW6.columnProductNumber'),
                    allowResize: true
                },
                {
                    property: 'name',
                    label: this.$t('TopdataDemoDataImporterSW6.columnName'),
                    allowResize: true
                },
                {
                    property: 'ean',
                    label: this.$t('TopdataDemoDataImporterSW6.columnEan'),
                    allowResize: true
                },
                {
                    property: 'mpn',
                    label: this.$t('TopdataDemoDataImporterSW6.columnMpn'),
                    allowResize: true
                }
            ];
        },

        hasDemoProducts() {
            return this.totalProducts > 0;
        }
    },

    methods: {
        /**
         * Fetches the current demo data status from the backend
         */
        async fetchDemoDataStatus() {
            this.isLoading = true;
            try {
                const { count, products } = await this.TopdataDemoDataApiService.getStatus();
                this.totalProducts = count;
                this.currentProducts = products;
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('TopdataDemoDataImporterSW6.statusErrorMessage')
                });
                console.error(error);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Triggers the demo data import via AJAX
         * Shows loading state and handles success/error notifications
         */
        async importDemoData() {
            this.isLoading = true;

            try {
                const response = await this.TopdataDemoDataApiService.installDemoData();
                if (response && response.importedProducts && response.importedProducts.length > 0) {
                    this.createNotificationSuccess({
                        message: this.$t('TopdataDemoDataImporterSW6.importedMessage', { count: response.importedProducts.length })
                    });
                } else {
                    this.createNotificationInfo({
                        message: this.$t('TopdataDemoDataImporterSW6.nothingToImportMessage')
                    });
                }
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('TopdataDemoDataImporterSW6.importErrorMessage')
                });
                console.error(error);
            } finally {
                await this.fetchDemoDataStatus();
            }
        },

        /**
         * Triggers the removal of demo data
         * Shows confirmation dialog and handles success/error notifications
         */
        async removeDemoData() {
            if (confirm(this.$t('TopdataDemoDataImporterSW6.removeConfirmText'))) {
                this.isLoading = true;

                try {
                    const response = await this.TopdataDemoDataApiService.removeDemoData();
                    if (response && response.deletedCount > 0) {
                        this.createNotificationSuccess({
                            message: this.$t('TopdataDemoDataImporterSW6.removeSuccessMessage', { count: response.deletedCount })
                        });
                    } else {
                        this.createNotificationInfo({
                            message: this.$t('TopdataDemoDataImporterSW6.nothingToRemoveMessage')
                        });
                    }
                } catch (error) {
                    this.createNotificationError({
                        message: this.$t('TopdataDemoDataImporterSW6.removeErrorMessage')
                    });
                    console.error(error);
                } finally {
                    await this.fetchDemoDataStatus();
                }
            }
        },

        /**
         * Creates a success notification
         * @param {Object} options
         * @param {string} options.message - The notification message
         */
        createNotificationSuccess({ message }) {
            this.createNotification({
                variant: 'success',
                title: this.$t('global.default.success'),
                message
            });
        },

        /**
         * Creates an error notification
         * @param {Object} options
         * @param {string} options.message - The notification message
         */
        createNotificationError({ message }) {
            this.createNotification({
                variant: 'error',
                title: this.$t('global.default.error'),
                message
            });
        },

        /**
         * Creates an info notification
         * @param {Object} options
         * @param {string} options.message - The notification message
         */
        createNotificationInfo({ message }) {
            this.createNotification({
                variant: 'info',
                title: this.$t('global.default.info'),
                message
            });
        }
    }
});
```

### 4. Frontend: Update Template

The template will be updated to show the grid of existing products, or a message if none are found.

**File:** `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/topdata-demo-data-index.html.twig`

```twig
{# Template for the demo data import page #}
{# Uses Shopware's UI components for consistent styling #}
<sw-page class="topdata-demo-data-index">
    <template #content>
        <sw-card-view>
            {# Main card containing the action buttons #}
            <sw-card
                    :isLoading="isLoading"
                    :large="true"
                    :title="$t('TopdataDemoDataImporterSW6.mainCardTitle')"
            >
                {# button to import demo data #}
                <sw-button
                        variant="primary"
                        size="large"
                        :disabled="isLoading"
                        @click="importDemoData"
                >
                    {{ $t('TopdataDemoDataImporterSW6.importButton') }}
                </sw-button>

                &nbsp;

                {# button to delete demo data #}
                <sw-button
                        variant="danger"
                        size="large"
                        :disabled="isLoading || !hasDemoProducts"
                        @click="removeDemoData"
                >
                    {{ $t('TopdataDemoDataImporterSW6.removeButton') }}
                </sw-button>
            </sw-card>

            {# Results card showing existing products #}
            <sw-card
                    v-if="hasDemoProducts && !isLoading"
                    :title="$t('TopdataDemoDataImporterSW6.resultsTitleExisting', { count: totalProducts })"
            >
                <sw-data-grid
                        :dataSource="currentProducts"
                        :columns="productColumns"
                        :show-selection="false"
                        :show-actions="false"
                />
            </sw-card>

            {# Card for when no demo products are found #}
            <sw-card v-if="!hasDemoProducts && !isLoading">
                <p>{{ $t('TopdataDemoDataImporterSW6.noProductsMessage') }}</p>
            </sw-card>
        </sw-card-view>
    </template>
</sw-page>
```

### 5. Frontend: Update Snippets (Text Labels)

Finally, let's add the new text snippets and remove the old, unused ones.

**File:** `src/Resources/app/administration/src/module/topdata-demo-data/snippet/de-DE.json`

```json
{
    "TopdataDemoDataImporterSW6": {
        "descriptionTextModule":  "Demo Daten importieren",
        "errorMessage":           "Beim Importieren der Demo Daten ist ein Fehler aufgetreten",
        "errorTitle":             "Fehler",
        "importButton":           "Demo Daten importieren",
        "importedMessage":        "Erfolgreich {count} Demo-Produkte importiert",
        "mainMenuTitle":          "Topdata Demo Daten",
        "nothingToImportMessage": "Keine neuen Demo-Produkte zum Importieren",
        "nothingToRemoveMessage": "Keine Demo-Produkte zum Löschen gefunden",
        "importErrorMessage":     "Beim Importieren der Demo-Produkte ist ein Fehler aufgetreten",
        "removeButton":           "Demo-Daten löschen",
        "removeConfirmText":      "Sind Sie sicher, dass Sie alle Demo-Produkte löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.",
        "removeErrorMessage":     "Fehler beim Löschen der Demo-Produkte",
        "removeSuccessMessage":   "Erfolgreich {count} Demo-Produkte gelöscht",
        "columnProductNumber":    "Artikelnummer",
        "columnName":             "Name",
        "columnEan":              "EAN",
        "columnMpn":              "Herst.-Art.-Nr.",
        "successMessage":         "Demo Daten wurden erfolgreich importiert",
        "successTitle":           "Erfolg",
        "mainCardTitle":          "Demo-Daten verwalten",
        "resultsTitleExisting":   "Vorhandene Demo-Produkte ({count})",
        "noProductsMessage":      "Es befinden sich aktuell keine Topdata Demo-Produkte im Shop.",
        "statusErrorMessage":     "Der Status der Demo-Daten konnte nicht abgerufen werden."
    }
}
```

**File:** `src/Resources/app/administration/src/module/topdata-demo-data/snippet/en-GB.json`

```json
{
    "TopdataDemoDataImporterSW6": {
        "descriptionTextModule":  "Import demo data",
        "errorMessage":           "An error occurred while importing demo data",
        "errorTitle":             "Error",
        "importButton":           "Import Demo Data",
        "importedMessage":        "Successfully imported {count} demo products",
        "mainMenuTitle":          "Topdata Demo Data",
        "nothingToImportMessage": "No new demo products to import",
        "nothingToRemoveMessage": "No demo products found to delete",
        "importErrorMessage":     "An error occurred while importing demo products",
        "removeButton":           "Delete Demo Data",
        "removeConfirmText":      "Are you sure you want to delete all demo products? This action cannot be undone.",
        "removeErrorMessage":     "An error occurred while deleting demo products",
        "removeSuccessMessage":   "Successfully deleted {count} demo products",
        "columnProductNumber":    "Product Number",
        "columnName":             "Name",
        "columnEan":              "EAN",
        "columnMpn":              "MPN",
        "successMessage":         "Demo data has been imported successfully",
        "successTitle":           "Success",
        "mainCardTitle":          "Manage Demo Data",
        "resultsTitleExisting":   "Existing Demo Products ({count})",
        "noProductsMessage":      "There are currently no Topdata demo products in the shop.",
        "statusErrorMessage":     "Could not fetch demo data status."
    }
}
```

After applying these changes, clear your Shopware cache and rebuild the administration (`bin/console administration:build`). Your plugin's admin page will now dynamically show the existing demo products upon loading and provide a much more intuitive experience.

