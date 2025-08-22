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
    installDemoData(): Promise<any> {
        return this.client.post('/topdata-demo-data/install-demodata', {});
    }

    /**
     * Removes demo data via the API.
     * @returns {Promise<{ status: string; deletedCount: number }>} - A promise that resolves with the API response containing status and deleted count.
     */
    removeDemoData(): Promise<{ status: string; deletedCount: number }> {
        return this.client
            .post('/topdata-demo-data/remove-demodata', {})
            .then((response: { data: { status: string; deletedCount: number } }) => {
                return response.data;
            });
    }
}
