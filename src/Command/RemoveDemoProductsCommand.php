<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataDemoDataImporterSW6\TopdataDemoDataImporterSW6;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;

/**
 * Command to remove all demo products created by this plugin.
 *
 * 07/2025 created
 */
#[AsCommand(
    name: 'topdata:demo-data-importer:remove-demo-products',
    description: 'Removes all demo products that were imported by this plugin.'
)]
class RemoveDemoProductsCommand extends AbstractTopdataCommand
{
    public function __construct(
        private readonly EntityRepository $productRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation and delete products immediately.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . TopdataDemoDataImporterSW6::CUSTOM_FIELD_IS_DEMO_PRODUCT, true));

        // Fetch full product details instead of just IDs
        $criteria->addAssociation('manufacturer');
        $products = $this->productRepository->search($criteria, $context);

        if ($products->getTotal() === 0) {
            $this->cliStyle->success('No demo products found to remove.');
            $this->done();
            return Command::SUCCESS;
        }

        $this->cliStyle->warning(sprintf('%d demo products will be permanently deleted.', $products->getTotal()));

        // Display table of products to be removed
        $this->cliStyle->section('Products to be removed');
        
        $tableHeaders = ['Product Number', 'Name', 'EAN', 'MPN'];
        $tableRows = [];
        
        foreach ($products->getEntities() as $product) {
            $tableRows[] = [
                $product->getProductNumber() ?? '',
                $product->getTranslation('name') ?? '',
                $product->getEan() ?? '',
                $product->getManufacturerNumber() ?? ''
            ];
        }
        
        $this->cliStyle->table($tableHeaders, $tableRows);
        $this->cliStyle->newLine();

        $force = $input->getOption('force');
        if (!$force && !$this->cliStyle->confirm('Are you sure you want to proceed?', true)) {
            $this->cliStyle->writeln('Aborted.');
            return Command::FAILURE;
        }

        // Extract IDs for deletion
        $idsToDelete = array_values(array_map(static fn ($product) => ['id' => $product->getId()], $products->getEntities()->getElements()));
        $this->productRepository->delete($idsToDelete, $context);

        $this->cliStyle->success(sprintf('Successfully deleted %d demo products.', $products->getTotal()));
        $this->done();

        return Command::SUCCESS;
    }
}