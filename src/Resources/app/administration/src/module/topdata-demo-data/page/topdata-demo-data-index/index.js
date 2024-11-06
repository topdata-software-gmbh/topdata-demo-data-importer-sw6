/**
 * Main component for the demo data import page
 * Handles the AJAX call to import demo data and displays notifications
 */

import template from './topdata-demo-data-index.html.twig';

Shopware.Component.register('topdata-demo-data-index', {
    template,

    inject: ['httpClient', 'TopdataDemoDataApiService'],

    data() {
        return {
            isLoading: false
        };
    },

    methods: {



        /**
         * Triggers the demo data import via AJAX
         * Shows loading state and handles success/error notifications
         */
        importDemoData() {
            this.isLoading = true;

            // Use the demo data service to import demo data
            this.TopdataDemoDataApiService
                .installDemoData()
                .then(() => {
                    // Show success notification
                    this.createNotificationSuccess({
                        title: this.$tc('topdata-demo-data.general.successTitle'),
                        message: this.$tc('topdata-demo-data.general.successMessage')
                    });
                })
                .catch((error) => {
                    // Show error notification
                    this.createNotificationError({
                        title: this.$tc('topdata-demo-data.general.errorTitle'),
                        message: error.response.data.message || this.$tc('topdata-demo-data.general.errorMessage')
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});
