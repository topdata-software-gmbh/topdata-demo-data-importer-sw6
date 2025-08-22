### Phase 1: Backend API Endpoint for Deletion

The first phase is to create a secure API endpoint that will handle the logic for deleting the demo products. This keeps the core business logic on the server side.

**Task 1.1: Enhance the API Controller**
1.  **Modify `src/Controller/TopdataDemoDataAdminApiController.php`:**
    *   Inject the `product.repository` service through the constructor to interact with product data.
    *   Create a new public method `removeDemoData()` with the route `/api/topdata-demo-data/remove-demodata`, restricted to `POST` requests.
    *   Inside this method, implement the logic to find all products marked as demo products using the custom field `topdata_demo_data_importer_is_demo_product`.
    *   Use the `productRepository` to delete the found products.
    *   Return a `JsonResponse` containing the status of the operation and the count of deleted products.
    *   Update the existing `installDemoData` route to use the `POST` method instead of `GET` for better adherence to web standards.

**Task 1.2: Update Service Configuration**
1.  **Modify `src/Resources/config/services.xml`:**
    *   Update the service definition for `TopdataDemoDataAdminApiController` to correctly inject the `product.repository` as a new constructor argument.

### Phase 2: Frontend API Service Integration

This phase connects the frontend Vue application with the new backend endpoint by updating the TypeScript API service.

**Task 2.1: Update the DemoDataApiService**
1.  **Modify `src/Resources/app/administration/src/module/topdata-demo-data/service/DemoDataApiService.ts`:**
    *   Add a new method called `removeDemoData()`. This method will send a `POST` request to the `/api/topdata-demo-data/remove-demodata` endpoint.
    *   Change the existing `installDemoData()` method to use a `POST` request, matching the backend change from Phase 1.

### Phase 3: User Interface and Component Logic

This phase focuses on updating the administration interface by adding the new delete button and implementing the client-side logic to handle user interactions.

**Task 3.1: Add Delete Button to the UI**
1.  **Modify `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/topdata-demo-data-index.html.twig`:**
    *   Add a new `<sw-button>` element next to the existing "Import Demo Data" button.
    *   Configure this button with `variant="danger"` to give it a distinct red color, indicating a destructive action.
    *   Label it using the appropriate text snippet (e.g., "Delete Demo Data").
    *   Bind its `@click` event to a new `removeDemoData` method in the component.

**Task 3.2: Implement Component Logic**
1.  **Modify `src/Resources/app/administration/src/module/topdata-demo-data/page/topdata-demo-data-index/index.js`:**
    *   Implement the `removeDemoData` method.
    *   Inside this method, use a native browser `confirm()` dialog to ask the user for confirmation before proceeding with the deletion.
    *   If the user confirms, set the `isLoading` state to `true`.
    *   Call the `removeDemoData()` method from the `TopdataDemoDataApiService`.
    *   Implement logic in the `.then()` block to show success notifications based on the response from the API (e.g., "X demo products were deleted" or "No demo products found").
    *   Implement logic in the `.catch()` block to show an error notification if the API call fails.
    *   Ensure `isLoading` is set back to `false` in a `.finally()` block.
    *   Refactor the notification logic for both import and delete actions into reusable helper methods (`createNotificationSuccess`, `createNotificationError`) to improve code quality and reduce duplication.
    *   Update the `importDemoData` method to use these new helpers and provide more detailed feedback.

### Phase 4: Localization Snippets

The final phase is to add all the necessary text for the new UI elements and notifications in both supported languages.

**Task 4.1: Add English Snippets**
1.  **Modify `src/Resources/app/administration/src/module/topdata-demo-data/snippet/en-GB.json`:**
    *   Add new JSON keys and their English translations for:
        *   The delete button label (`removeButton`).
        *   The confirmation dialog text (`removeConfirmText`).
        *   Success and info messages for import and delete actions (`importedMessage`, `nothingToImportMessage`, `removeSuccessMessage`, `nothingToRemoveMessage`).
        *   Error messages (`removeErrorMessage`).

**Task 4.2: Add German Snippets**
1.  **Modify `src/Resources/app/administration/src/module/topdata-demo-data/snippet/de-DE.json`:**
    *   Add the corresponding German translations for all the new keys added in the previous step.

