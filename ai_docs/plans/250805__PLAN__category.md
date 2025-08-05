## Implementation Plan: Interactive Category Assignment for Product Import

The goal is to enhance the `topdata:demo-data-importer:import-demo-products` command to allow assigning imported products to a category. This will be achieved through three modes of operation:
1.  **Direct Mode:** User specifies a category ID via a `--category-id` option.
2.  **No-Category Mode:** User explicitly opts out of category assignment via a `--no-category` option.
3.  **Interactive Mode (Default):** If neither of the above options is provided, the command will prompt the user to select a category from a list of existing categories.

### Phase 1: Foundational Backend Enhancements

This phase focuses on updating the service layer to handle category data. We will modify the services to accept a category ID and add it to the product data payload.

#### Task 1.1: Modify `ProductService` to Accept a Category ID
The `ProductService` is responsible for building the product data array. We need to update `formProductsArray` to include category information if a category ID is provided.

**File to Edit:** `src/Service/ProductService.php`

1.  Modify the `formProductsArray` method signature to accept an optional, nullable category ID.
2.  Inside the method, if a `$categoryId` is provided, add the `categories` key to the product data array.

```php
// src/Service/ProductService.php

// ...
// Add an optional, nullable string for the category ID
public function formProductsArray(array $input, float $price = 1.0, ?string $categoryId = null): array
{
    $output = [];
    $taxId = $this->getTaxId();
    // ...

    foreach ($input as $in) {
        $prod = [
            // ... existing product data
            'customFields' => [
                TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT => true,
            ],
        ];

        // START of new code
        if ($categoryId) {
            $prod['categories'] = [
                ['id' => $categoryId],
            ];
        }
        // END of new code

        if (isset($in['description'])) {
            // ...
        }
        // ...
        $output[] = $prod;
    }

    return $output;
}
```

#### Task 1.2: Update `DemoDataImportService` to Pass Through the Category ID
The `DemoDataImportService` orchestrates the import. It needs to accept the category ID from the command and pass it down to the `ProductService`.

**File to Edit:** `src/Service/DemoDataImportService.php`

1.  Modify the `installDemoData` method signature to accept an optional, nullable category ID.
2.  Pass this ID to the `productService->formProductsArray()` call.

```php
// src/Service/DemoDataImportService.php

// ...
// Add an optional, nullable string for the category ID
public function installDemoData(string $filename = 'demo-products.csv', ?string $categoryId = null): array
{
    // ... (file reading logic remains the same)

    // ...
    $products = $this->productService->clearExistingProductsByProductNumber($products);
    if (count($products)) {
        // Pass the categoryId to the formProductsArray method
        $products = $this->productService->formProductsArray($products, 100000.0, $categoryId);
    } else {
    // ...
}
```

### Phase 2: Implementing Command-Line Options

Now we will update the `ImportDemoProductsCommand` to include the new command-line options and the logic to handle them.

#### Task 2.1: Add New Options to the Command
We'll add `--category-id`, `--no-category`, and `--category-name` to the command's configuration.

**File to Edit:** `src/Command/ImportDemoProductsCommand.php`

1.  In the `configure()` method, add the new options.

```php
// src/Command/ImportDemoProductsCommand.php

// ...
protected function configure(): void
{
    $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
    
    // START of new code
    $this->addOption('category-id', null, InputOption::VALUE_REQUIRED, 'Assign products to a specific category by its ID.');
    $this->addOption('no-category', null, InputOption::VALUE_NONE, 'Do not assign products to any category.');
    // END of new code
}
// ...
```

#### Task 2.2: Add Logic to `execute()` to Handle Options
The `execute` method will contain the primary logic to decide which mode to run in.

**File to Edit:** `src/Command/ImportDemoProductsCommand.php`

1.  Retrieve the option values at the beginning of the `execute()` method.
2.  Add validation to prevent conflicting options (e.g., using `--category-id` and `--no-category` together).
3.  Pass the resolved category ID to the `demoDataImportService`.

```php
// src/Command/ImportDemoProductsCommand.php

// ...
public function execute(InputInterface $input, OutputInterface $output): int
{
    // ... (confirmation logic)

    // START of new code
    $categoryId = $input->getOption('category-id');
    $noCategory = $input->getOption('no-category');

    if ($categoryId && $noCategory) {
        $this->cliStyle->error('The options --category-id and --no-category cannot be used together.');
        return Command::INVALID;
    }
    // END of new code, for now. Interactive logic comes in Phase 3.

    $result = $this->demoDataImportService->installDemoData('demo-products.csv', $categoryId);

    // ... (rest of the method)
}
```

### Phase 3: Implementing Interactive Category Selection

This phase implements the default behavior when no category option is specified. The user will be prompted to choose a category.

#### Task 3.1: Inject Category Repository into the Command
To fetch a list of categories, we need access to the `category.repository`.

**File to Edit:** `src/Command/ImportDemoProductsCommand.php`

1.  Inject `category.repository` through the constructor.

```php
// src/Command/ImportDemoProductsCommand.php

// ...
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class ImportDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
        // START of new code
        private readonly EntityRepository $categoryRepository
        // END of new code
    ) {
        parent::__construct();
    }
    // ...
}
```

**File to Edit:** `src/Resources/config/services.xml`

2.  Ensure the new dependency is injected correctly by adding an argument to the service definition.

```xml
<!-- src/Resources/config/services.xml -->
<service id="Topdata\TopdataDemoDataImporterSW6\Command\ImportDemoProductsCommand" autowire="true">
    <argument type="service" id="category.repository"/>
    <tag name="console.command"/>
</service>
```
*Note: If autowiring is fully effective, this explicit argument might not be needed, but it's safer to be explicit.*

#### Task 3.2: Implement Interactive Logic in `execute()`

1.  If neither `--category-id` nor `--no-category` is set, fetch categories and prompt the user.
2.  Use `SymfonyStyle`'s `choice()` method to present the options.
3.  Handle the user's selection.

**File to Edit:** `src/Command/ImportDemoProductsCommand.php`

```php
// src/Command/ImportDemoProductsCommand.php

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
// ...

public function execute(InputInterface $input, OutputInterface $output): int
{
    // ... (warning and confirmation logic) ...
    
    $categoryId = $input->getOption('category-id');
    $noCategory = $input->getOption('no-category');

    if ($categoryId && $noCategory) {
        $this->cliStyle->error('The options --category-id and --no-category cannot be used together.');
        return Command::INVALID;
    }

    // START: Interactive Logic
    if (!$categoryId && !$noCategory) {
        $categoryId = $this->_getCategoryFromInteractiveChoice();
        if ($categoryId === null && !$noCategory) {
             $this->cliStyle->writeln('No category selected. Aborting.');
             return Command::SUCCESS; // Or FAILURE, depending on desired behavior
        }
    }
    // END: Interactive Logic

    // ... (call to demoDataImportService)
    $result = $this->demoDataImportService->installDemoData('demo-products.csv', $categoryId);
    
    // ... (success message and table rendering) ...
}


// START: New private helper method
private function _getCategoryFromInteractiveChoice(): ?string
{
    $context = Context::createDefaultContext();
    $criteria = new Criteria();
    $criteria->addAssociation('translation');
    $categories = $this->categoryRepository->search($criteria, $context)->getEntities();

    if ($categories->count() === 0) {
        $this->cliStyle->note('No categories found in the system. Importing products without category assignment.');
        return null;
    }

    $choices = [];
    foreach ($categories as $category) {
        $name = $category->getTranslation('name') ?? $category->getName() ?? $category->getId();
        $choices[$name] = $category->getId();
    }

    $noCategoryOption = 'Do not assign to any category';
    $choices[$noCategoryOption] = null;

    $selectedCategoryName = $this->cliStyle->choice(
        'Please select a category to assign the demo products to',
        array_keys($choices)
    );
    
    return $choices[$selectedCategoryName];
}
// END: New private helper method
```

### Phase 4: Documentation and Final Touches

The final step is to update the documentation to reflect the new functionality and improve user feedback.

#### Task 4.1: Update `README.md`
Document the new options and the interactive mode.

**File to Edit:** `README.md`

-   Under the `topdata:demo-data-importer:import-demo-products` command section, add explanations and examples for the new options.

```markdown
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
```

#### Task 4.2: Enhance User Feedback
Improve the success message to confirm the category assignment.

**File to Edit:** `src/Command/ImportDemoProductsCommand.php`

- In the `execute` method, after the import, fetch the category name (if an ID was used) and display it in the success message.

