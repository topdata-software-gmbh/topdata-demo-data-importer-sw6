/**
 * Fix for "TS2304: Cannot find name Shopware"
 * TODO: check https://developer.shopware.com/docs/guides/plugins/plugins/administration/the-shopware-object.html
 */
declare var Shopware: any;

const ApiService = Shopware.Classes.ApiService;

/**
 * Service for handling demo data operations in the admin interface
 */
export default class DemoDataApiService extends ApiService {

    /**
     * Constructor for TopdataApiCredentialsService.
     * @param {Object} httpClient - The HTTP client for making requests.
     * @param {Object} loginService - The login service for authentication.
     * @param {string} [apiEndpoint='topdata'] - The API endpoint for Topdata.
     */
    constructor(httpClient, loginService, apiEndpoint = '') {
        super(httpClient, loginService, apiEndpoint);
    }


    /**
     * Installs demo data via the API.
     * @returns {Promise} - A promise that resolves with the API response.
     */
    installDemoData() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `${this.getApiBasePath()}/connector-install-demodata`,
                {
                    params: {},
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}
