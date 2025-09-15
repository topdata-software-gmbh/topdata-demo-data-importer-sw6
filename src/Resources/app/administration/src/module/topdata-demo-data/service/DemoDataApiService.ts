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
     * @returns {Promise<any>} - A promise that resolves with the API response.
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
