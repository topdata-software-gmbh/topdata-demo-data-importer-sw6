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
