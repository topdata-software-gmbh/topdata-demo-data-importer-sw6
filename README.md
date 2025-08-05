# With this plugin you can import demo data

![topdata-demo-data-importer-sw6-256x256.png](src/Resources/config/topdata-demo-data-importer-sw6-256x256.png)

## Installation

1. Download the plugin
2. Upload to your Shopware 6 installation
3. Install and activate the plugin

## Requirements

- Shopware 6.5.* or 6.6.*


## Commands

### topdata:demo-data-importer:import-demo-products
- imports demo products from the bundled demo-products.csv file

This command supports three modes for category assignment:

1.  **Interactive Mode (Default):** Run the command without category options to get an interactive prompt to choose a category.
    ```bash
    bin/console topdata:demo-data-importer:import-demo-products
    ```

2.  **Direct Assignment:** Use the `--category-id` option to assign products directly to a category.
    ```bash
    bin/console topdata:demo-data-importer:import-demo-products --category-id=your-category-id-here
    ```

3.  **No Category:** Use the `--no-category` flag to import products without assigning them to any category.
    ```bash
    bin/console topdata:demo-data-importer:import-demo-products --no-category
    ```

#### Usage Examples

**Example 1: Interactive Category Selection**
```bash
$ bin/console topdata:demo-data-importer:import-demo-products

 This will import demo products into your shop.

 Please select a category to assign the demo products to:
  [0] Home & Garden
  [1] Electronics
  [2] Clothing
  [3] Do not assign to any category
 > 1

 [OK] Demo data imported successfully! Products have been assigned to category: Electronics
```

**Example 2: Direct Category Assignment**
```bash
$ bin/console topdata:demo-data-importer:import-demo-products --category-id=019123456789abcdef

 [OK] Demo data imported successfully! Products have been assigned to category: Electronics
```

**Example 3: Import Without Category**
```bash
$ bin/console topdata:demo-data-importer:import-demo-products --no-category

 [OK] Demo data imported successfully! Products have been imported without category assignment.
```

**Example 4: Force Import Without Confirmation**
```bash
$ bin/console topdata:demo-data-importer:import-demo-products --category-id=019123456789abcdef --force

 [OK] Demo data imported successfully! Products have been assigned to category: Electronics
```

### topdata:demo-data-importer:use-webservice-demo-credentials
- sets up demo credentials for the Topdata webservice
- example usage:

```bash
bin/console topdata:demo-data-importer:use-webservice-demo-credentials
```

### topdata:demo-data-importer:import-products-csv
- a console command for import products from csv file
- example usage:

```bash
bin/console topdata:demo-data-importer:import-products-csv --file=prods2020-07-26.csv --start=1 --end=1000 --number=4 --wsid=4 --name=11 --brand=10
```

`--file`  specify filename

`--start`  start line of a file, default is 1 (first line is 0, it usually have column titles)

`--end`  end line of a file, by default file will be read until the end

`--number`  column with unique product number

`--wsid`  column with Webservice id (if csv is given from TopData it may have this column), if it is set product will be mapped to Top Data Webserivce products

`--name`  column with product name

`--brand`  column with product brand name (will be created if is not present yet)

It is recommended to limit product count with start/end, depending on server RAM. Then you can read next chunk of products in second command.

### topdata:demo-data-importer:remove-demo-products
- removes all demo products that were imported by this plugin
- includes a confirmation prompt for safety
- example usage:

```bash
bin/console topdata:demo-data-importer:remove-demo-products
```

To skip the confirmation prompt (use with caution):

```bash
bin/console topdata:demo-data-importer:remove-demo-products --force
```



## License

MIT
