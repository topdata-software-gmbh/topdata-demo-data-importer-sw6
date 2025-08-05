---
title: Usage
---
# Using the Plugin

## Admin Interface
1. Navigate to Topdata Demo Data in admin menu
2. Click "Import Demo Data" to import bundled products

## Console Commands
### Import Demo Products
```bash
bin/console topdata:demo-data-importer:import-demo-products [--category-id=ID] [--no-category] [--force]
```

### Remove Demo Products
```bash
bin/console topdata:demo-data-importer:remove-demo-products [--force]
```

### Set Webservice Credentials
```bash
bin/console topdata:demo-data-importer:use-webservice-demo-credentials
```

### Custom CSV Import
```bash
bin/console topdata:demo-data-importer:import-products-csv --file=products.csv --number=4 --name=2 --brand=3