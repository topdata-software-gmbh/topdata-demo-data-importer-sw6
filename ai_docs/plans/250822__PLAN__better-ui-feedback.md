### Phase 1: Backend API Enhancements

The goal of this phase is to modify the backend API controller to return detailed information about the products being modified (imported or deleted), not just a success status or count.

#### Task 1.1: Enhance `removeDemoData` API Response
Modify the `removeDemoData` method in the API controller to fetch full product details before deletion and include them in the JSON response.

**File to Edit:** `src/Controller/TopdataDemoDataAdminApiController.php`

1.  Locate the `removeDemoData` method.
2.  Change the `productRepository->searchIds()` call to `productRepository->search()` to retrieve the full product entities.
3.  Before deleting the products, create a new array (`deletedProductsData`).
4.  Iterate over the found product entities and map their essential details (`productNumber`, `name`, `ean`, `mpn`) to the `deletedProductsData` array.
5.  Extract the product IDs into a separate array for the `delete` operation.
6.  Update the final `JsonResponse` to include the `deletedProductsData` array under the key `deletedProducts`.
7.  Ensure the `deletedCount` is still correctly calculated and returned.

#### Task 1.2: Verify `installDemoData` API Response
Confirm that the `installDemoData` method already returns the necessary product details.

**File to Edit:** `src/Controller/TopdataDemoDataAdminApiController.php`

1.  Review the `installDemoData` method.
2.  Confirm that the `$result` from `$this->demoDataImportService->installDemoData()` contains an `importedProducts` key with an array of product details. *No changes should be required here, as the service already provides this.*
3.  Ensure the method returns the entire `$result` object in the `JsonResponse`.

### Phase 2: Frontend Service and Localization

This phase prepares the frontend by updating the API service to handle the new response structure and adding the necessary text snippets for the new UI elements.

#### Task 2.1: Update Frontend API Service
Adjust the TypeScript API service to correctly type the enhanced response from the `removeDemoData` endpoint.

**File to Edit:** `src/Resources/app/administration/src/module/topdata-demo-data/service/DemoDataApiService.ts`

1.  Find the `removeDemoData` method.
2.  Update its return `Promise` type to include `deletedProducts: Array<any>`. The new signature should be `Promise<{ status: string; deletedCount: number; deletedProducts: Array<any> }>`.
3.  Modify the `.then()` block to ensure the entire `response.data` object is returned, which now contains the product list.
4.  Update the `installDemoData` method to parse the response correctly by returning `response.data`.

#### Task 2.2: Add Localization Snippets
Add new German and English text for the results table title and column headers.

**File to Edit:** `src/Resources/app/administration/src/module/topdata-demo-data/snippet/de-DE.json`

1.  Add the following new keys and their German translations inside the `TopdataDemoDataImporterSW6` object:
    *   `resultsTitleImported`: "Importierte Produkte"
    *   `resultsTitleRemoved`: "Gelöschte Produkte"
    *   `columnProductNumber`: "Artikelnummer"
    *   `columnName`: "Name"
    *   `columnEan`: "EAN"
    *   `columnMpn`: "Herst.-Art.-Nr."

**File to Edit:** `src/Resources/app/administration/src/module/topdata-demo-data/snippet/en-GB.json`

1.  Add the following new keys and their English translations inside the `TopdataDemoDataImporterSW6` object:
    *   `resultsTitleImported`: "Imported Products"
    *   `resultsTitleRemoved`: "Deleted Products"
    *   `columnProductNumber`: "Product Number"
    *   `columnName`: "Name"
    *   `columnEan`: "EAN"
    *   `columnMpn`: "MPN"

### Phase 3: Frontend UI and Logic Implementation

This phase focuses on updating the administration interface to display the results table and handle the user interaction logic.

#### Task 3.1: Update the UI Template
Modify the Twig template to include a data grid for displaying results and improve the button styling.

**File to Edit:** `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/topdata-demo-data-index.html.twig`

1.  Change the `variant` of the "Delete" button (`sw-button` for `removeDemoData`) from `primary` to `danger`.
2.  Add `&nbsp;` between the two `sw-button` elements for better visual spacing.
3.  Below the existing action card, add a new `<sw-card>` component.
4.  Use the `v-if="processedProducts && processedProducts.length > 0"` directive on this new card to make it visible only when there is data to display.
5.  Bind the card's `title` attribute to a new data property: `:title="resultTitle"`.
6.  Inside the new card, add an `<sw-data-grid>` component.
7.  Configure the data grid with the following bindings:
    *   `:dataSource="processedProducts"`
    *   `:columns="productColumns"`
    *   `:show-selection="false"`
    *   `:show-actions="false"`

#### Task 3.2: Implement Component Logic
Update the Vue component's Javascript to manage the state for the results table and handle the data from the API calls.

**File to Edit:** `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/index.js`

1.  In the `data()` method, add two new properties: `processedProducts: []` and `resultTitle: ''`.
2.  Add a `computed` property section to the component.
3.  Inside `computed`, create a method `productColumns()` that returns an array of column definitions for the data grid. Each column object should have `property`, `label` (using `$t` with the new snippets), and `allowResize: true`.
4.  In the `importDemoData` method, before the API call, reset `this.processedProducts = []` and `this.resultTitle = ''`.
5.  In the `.then()` block of `importDemoData`, if `response.importedProducts.length > 0`:
    *   Assign the product list: `this.processedProducts = response.importedProducts;`
    *   Set the title: `this.resultTitle = this.$t('TopdataDemoDataImporterSW6.resultsTitleImported');`
6.  In the `removeDemoData` method, before the API call, reset `this.processedProducts = []` and `this.resultTitle = ''`.
7.  In the `.then()` block of `removeDemoData`, if `response.deletedCount > 0`:
    *   Assign the product list: `this.processedProducts = response.deletedProducts;`
    *   Set the title: `this.resultTitle = this.$t('TopdataDemoDataImporterSW6.resultsTitleRemoved');`


