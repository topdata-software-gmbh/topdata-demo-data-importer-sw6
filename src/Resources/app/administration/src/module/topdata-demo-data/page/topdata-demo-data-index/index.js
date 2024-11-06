/**
 * Main component for the demo data import page
 * Handles the AJAX call to import demo data and displays notifications
 */

import template from './topdata-demo-data-index.html.twig';

Shopware.Component.register('topdata-demo-data-index', {
    template,

    inject: ['TopdataDemoDataApiService'],

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
            this.TopdataDemoDataApiService.installDemoData().finally(() => {
                this.isLoading = false;
            });
        }
    }
});
