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
            this.TopdataDemoDataApiService.installDemoData()
                .then(response => {
                    if (response.importedCount > 0) {
                        this.createNotificationSuccess({
                            message: this.$tc('TopdataDemoDataImporterSW6.importedMessage', response.importedCount)
                        });
                    } else {
                        this.createNotificationInfo({
                            message: this.$tc('TopdataDemoDataImporterSW6.nothingToImportMessage')
                        });
                    }
                })
                .catch(error => {
                    this.createNotificationError({
                        message: this.$tc('TopdataDemoDataImporterSW6.importErrorMessage')
                    });
                    console.error(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        /**
         * Triggers the removal of demo data
         * Shows confirmation dialog and handles success/error notifications
         */
        removeDemoData() {
            if (confirm(this.$tc('TopdataDemoDataImporterSW6.removeConfirmText'))) {
                this.isLoading = true;
                this.TopdataDemoDataApiService.removeDemoData()
                    .then(response => {
                        if (response.deletedCount > 0) {
                            this.createNotificationSuccess({
                                message: this.$tc('TopdataDemoDataImporterSW6.removeSuccessMessage', response.deletedCount)
                            });
                        } else {
                            this.createNotificationInfo({
                                message: this.$tc('TopdataDemoDataImporterSW6.nothingToRemoveMessage')
                            });
                        }
                    })
                    .catch(error => {
                        this.createNotificationError({
                            message: this.$tc('TopdataDemoDataImporterSW6.removeErrorMessage')
                        });
                        console.error(error);
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
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
                title: this.$tc('global.default.success'),
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
                title: this.$tc('global.default.error'),
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
                title: this.$tc('global.default.info'),
                message
            });
        }
    }
});
