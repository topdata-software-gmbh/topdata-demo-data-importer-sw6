<?php

declare(strict_types=1);

namespace Topdata\TopdataDemoDataImporterSW6\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CategorySelectorService implements CategorySelectorServiceInterface
{
    public function __construct(
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function getCategoryChoices(): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addAssociation('parent');
        $criteria->setLimit(100);

        $categories = $this->categoryRepository->search($criteria, Context::createDefaultContext());

        if ($categories->count() === 0) {
            return [];
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
                'id'          => $category->getId(),
                'depth'       => $depth,
                'displayName' => $displayName,
            ];
        }

        usort($categoriesData, function (array $a, array $b): int {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] <=> $b['depth'];
            }
            return strcmp($a['displayName'], $b['displayName']);
        });

        $choices = [];
        foreach ($categoriesData as $data) {
            $choices[$data['id']] = $data['displayName'];
        }

        return $choices;
    }

    public function getCategoryName(string $categoryId): ?string
    {
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, Context::createDefaultContext())->first();

        return $category instanceof CategoryEntity ? $category->getName() : null;
    }

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
