<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to import products from a CSV file into Shopware 6
 *
 * 11/2024 created
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:import-demo-products',
    description: 'Import demo products into the shop'
)]
class ImportDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly DemoDataImportService $demoDataImportService,
        private readonly EntityRepository $categoryRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
        $this->addOption('category-id', null, InputOption::VALUE_REQUIRED, 'Import products into a specific category by ID');
        $this->addOption('no-category', null, InputOption::VALUE_NONE, 'Import products without assigning them to any category');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cliStyle->warning('This will import demo products into your shop.');
        
        $categoryId = $input->getOption('category-id');
        $noCategory = $input->getOption('no-category');
        
        if ($categoryId !== null && $noCategory) {
            $this->cliStyle->error('Options --category-id and --no-category cannot be used together.');
            return Command::FAILURE;
        }
        
        // Interactive category selection when no category options are provided
        if ($categoryId === null && !$noCategory) {
            $categoryId = $this->_getCategoryFromInteractiveChoice();
            if ($categoryId === null) {
                $this->cliStyle->error('No category selected. Aborting.');
                return Command::FAILURE;
            }
        }
        
        $force = $input->getOption('force');
        if (!$force && !$this->cliStyle->confirm('Are you sure you want to proceed?', true)) {
            $this->cliStyle->writeln('Aborted.');
            return Command::FAILURE;
        }

        $result = $this->demoDataImportService->installDemoData('demo-products.csv', $categoryId);

        if (isset($result['importedProducts']) && is_array($result['importedProducts'])) {
            $this->cliStyle->section('Imported Articles');
            
            $tableHeaders = ['Product Number', 'Name', 'EAN', 'MPN'];
            $tableRows = [];
            
            foreach ($result['importedProducts'] as $product) {
                $tableRows[] = [
                    $product['productNumber'] ?? '',
                    $product['name'] ?? '',
                    $product['ean'] ?? '',
                    $product['mpn'] ?? ''
                ];
            }
            
            $this->cliStyle->table($tableHeaders, $tableRows);
            $this->cliStyle->newLine();
        }

        $categoryName = $categoryId ? $this->getCategoryName($categoryId) : null;
        
        $successMessage = 'Demo data imported successfully!';
        if ($categoryName) {
            $successMessage .= sprintf(' Products have been assigned to category: %s', $categoryName);
        } elseif ($noCategory) {
            $successMessage .= ' Products have been imported without category assignment.';
        } else {
            $successMessage .= ' Products have been imported.';
        }
        
        $this->cliStyle->success($successMessage);
        $this->cliStyle->writeln("Consider to run <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }

    /**
     * Get category name by ID
     */
    private function getCategoryName(string $categoryId): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();
        
        return $category instanceof CategoryEntity ? $category->getName() : null;
    }

    /**
     * Interactive category selection helper
     */
    private function _getCategoryFromInteractiveChoice(): ?string
    {
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $criteria->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('name'));
        $criteria->addAssociation('parent');
        $criteria->setLimit(100);

        $categories = $this->categoryRepository->search($criteria, \Shopware\Core\Framework\Context::createDefaultContext());
        
        if ($categories->count() === 0) {
            $this->cliStyle->warning('No categories found in the system.');
            return null;
        }

        // Build category tree for breadcrumb generation
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getId()] = $category;
        }

        // Prepare an array to hold category data for sorting
        $categoriesData = [];
        foreach ($categories as $category) {
            /** @var CategoryEntity $category */
            $breadcrumb = $this->buildBreadcrumb($category, $categoryMap);
            $depth = count($breadcrumb);
            $displayName = implode(' > ', $breadcrumb);
            
            $categoriesData[] = [
                'category' => $category,
                'breadcrumb' => $breadcrumb,
                'depth' => $depth,
                'displayName' => $displayName,
            ];
        }

        // Sort categories by depth (shallowest first) and then by display name
        usort($categoriesData, function($a, $b) {
            // First, compare by depth
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] - $b['depth'];
            }
            // If same depth, compare by display name
            return strcmp($a['displayName'], $b['displayName']);
        });

        $choices = [];
        foreach ($categoriesData as $data) {
            $category = $data['category'];
            $choices[$category->getId()] = $data['displayName'];
        }

        $this->cliStyle->section('Category Selection');
        $this->cliStyle->writeln('Please select a category to import the demo products into:');
        
        $selectedCategoryId = $this->cliStyle->choice(
            'Select category',
            $choices,
            array_key_first($choices)
        );

        return $selectedCategoryId;
    }

    /**
     * Build breadcrumb path for a category
     *
     * @param CategoryEntity $category
     * @param array<string, CategoryEntity> $categoryMap
     * @return string[]
     */
    private function buildBreadcrumb(CategoryEntity $category, array $categoryMap): array
    {
        $breadcrumb = [];
        $current = $category;
        
        // Build breadcrumb from current category up to root
        while ($current !== null) {
            array_unshift($breadcrumb, $current->getName() ?? 'Unnamed Category');
            
            // Check if parent exists in our loaded categories
            $parentId = $current->getParentId();
            if ($parentId && isset($categoryMap[$parentId])) {
                $current = $categoryMap[$parentId];
            } else {
                $current = null;
            }
        }
        
        return $breadcrumb;
    }
}
