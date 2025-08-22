/**
 * This module registers the Topdata Demo Data administration interface
 * It creates a new menu entry and defines the route for the demo data import page
 */

import './page/topdata-demo-data-index';
import DemoDataApiService from './service/DemoDataApiService';

Shopware.Module.register('topdata-demo-data', {
    type: 'plugin',
    name: 'Topdata Demo Data',
    title: 'TopdataDemoDataImporterSW6.mainMenuTitle',
    description: 'TopdataDemoDataImporterSW6.descriptionTextModule',
    color: '#ff3d58',

    // Define the available routes for this module
    routes: {
        index: {
            component: 'topdata-demo-data-index',
            path: 'index'
        }
    },

    // Configure the menu entry in the administration
    navigation: [{
        label: 'TopdataDemoDataImporterSW6.mainMenuTitle',
        color: '#ff3d58',
        path: 'topdata.demo.data.index',
        icon: 'regular-database',
        position: 100,
        parent: 'sw-content'
    }]
});

// Register the demo data service
Shopware.Service().register('TopdataDemoDataApiService', () => {
    return new DemoDataApiService();
});
