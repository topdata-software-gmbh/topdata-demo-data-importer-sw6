<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\Service\DemoDataImportService;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;

/**
 * Command for importing the standard bundled product CSV dataset.
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
    ) {
        parent::__construct();
    }

    /**
     * Configures the input options.
     */
    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and import products immediately.');
        $this->addOption('category-id', null, InputOption::VALUE_REQUIRED, 'Import products into a specific category by ID');
        $this->addOption('no-category', null, InputOption::VALUE_NONE, 'Import products without assigning them to any category');
    }

    /**
     * Links custom cliStyle output to the global CliLogger service wrapper.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        CliLogger::setCliStyle($this->cliStyle);
    }

    /**
     * Core execution logic of the command.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        CliLogger::warning('This will import demo products into your shop.');
        
        $categoryId = $input->getOption('category-id');
        $noCategory = $input->getOption('no-category');
        
        if ($categoryId !== null && $noCategory) {
            CliLogger::error('Options --category-id and --no-category cannot be used together.');
            return Command::FAILURE;
        }
        
        if ($categoryId === null && !$noCategory) {
            $categoryId = $this->_getCategoryFromInteractiveChoice();
            if ($categoryId === null) {
                CliLogger::error('No category selected. Aborting.');
                return Command::FAILURE;
            }
        }
        
        $force = $input->getOption('force');
        if (!$force && !CliLogger::getCliStyle()->confirm('Are you sure you want to proceed?', true)) {
            CliLogger::writeln('Aborted.');
            return Command::FAILURE;
        }

        $result = $this->demoDataImportService->installDemoData('demo-products.csv', $categoryId);

        if (isset($result['importedProducts']) && is_array($result['importedProducts'])) {
            CliLogger::section('Imported Articles');
            
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
            
            CliLogger::getCliStyle()->table($tableHeaders, $tableRows);
            CliLogger::writeln('');
        }

        $categoryName = $categoryId ? $this->_getCategoryName($categoryId) : null;
        
        $successMessage = 'Demo data imported successfully!';
        if ($categoryName) {
            $successMessage .= sprintf(' Products have been assigned to category: %s', $categoryName);
        } elseif ($noCategory) {
            $successMessage .= ' Products have been imported without category assignment.';
        } else {
            $successMessage .= ' Products have been imported.';
        }
        
        CliLogger::success($successMessage);
        CliLogger::writeln("Consider to run <info>topdata:connector:import</info> command to enrich the products with additional data.");

        $this->done();

        return Command::SUCCESS;
    }

    /**
     * Fetches a category name by UUID.
     */
    private function _getCategoryName(string $categoryId): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();
        
        return $category instanceof CategoryEntity ? $category->getName() : null;
    }

    /**
     * Interactively asks the user to pick a category.
     */
    private function _getCategoryFromInteractiveChoice(): ?string
    {
        $criteria = new Criteria();
        $criteria->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('name'));
        $criteria->addAssociation('parent');
        $criteria->setLimit(100);

        $categories = $this->categoryRepository->search($criteria, Context::createDefaultContext());
        
        if ($categories->count() === 0) {
            CliLogger::warning('No categories found in the system.');
            return null;
        }

        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getId()] = $category;
        }

        $categoriesData = [];
        foreach ($categories as $category) {
            /** @var CategoryEntity $category */
            $breadcrumb = $this->_buildBreadcrumb($category, $categoryMap);
            $depth = count($breadcrumb);
            $displayName = implode(' > ', $breadcrumb);
            
            $categoriesData[] = [
                'category' => $category,
                'breadcrumb' => $breadcrumb,
                'depth' => $depth,
                'displayName' => $displayName,
            ];
        }

        usort($categoriesData, function($a, $b) {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] - $b['depth'];
            }
            return strcmp($a['displayName'], $b['displayName']);
        });

        $choices = [];
        foreach ($categoriesData as $data) {
            $category = $data['category'];
            $choices[$category->getId()] = $data['displayName'];
        }

        CliLogger::section('Category Selection');
        CliLogger::writeln('Please select a category to import the demo products into:');
        
        return CliLogger::getCliStyle()->choice(
            'Select category',
            $choices,
            array_key_first($choices)
        );
    }

    /**
     * Helper to build visual string tree paths of parent-child category relations.
     */
    private function _buildBreadcrumb(CategoryEntity $category, array $categoryMap): array
    {
        $breadcrumb = [];
        $current = $category;
        
        while ($current !== null) {
            array_unshift($breadcrumb, $current->getName() ?? 'Unnamed Category');
            
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
