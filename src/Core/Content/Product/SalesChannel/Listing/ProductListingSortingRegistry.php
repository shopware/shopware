<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @deprecated tag:v6.4.0 sortings are now stored in database
 * @see /platform/adr/2020-08-14-implement-individual-sorting.md on how to adapt to the new sortings logic
 */
class ProductListingSortingRegistry
{
    /**
     * @var ProductListingSorting[]
     */
    protected $sortings = [];

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(iterable $sortings, TranslatorInterface $translator)
    {
        foreach ($sortings as $sorting) {
            $this->add($sorting);
        }

        $this->translator = $translator;
    }

    public function add(ProductListingSorting $sorting): void
    {
        $this->sortings[$sorting->getKey()] = $sorting;
    }

    public function get(string $key): ?ProductListingSorting
    {
        return $this->sortings[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->sortings[$key]);
    }

    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function getProductSortingEntities(?array $availableSortings = null): ProductSortingCollection
    {
        $productSortings = new ProductSortingCollection();

        foreach ($this->getSortings() as $sorting) {
            if ($availableSortings && !\in_array($sorting->getKey(), $availableSortings, true)) {
                continue;
            }

            $productSortings->add($this->convertToProductSortingEntity($sorting));
        }

        return $productSortings;
    }

    private function convertToProductSortingEntity(ProductListingSorting $sorting): ProductSortingEntity
    {
        $fields = \array_map(function ($field, $order) {
            return ['field' => $field, 'order' => $order, 'priority' => 0, 'naturalSorting' => 0];
        }, \array_keys($sorting->getFields()), $sorting->getFields());

        $productSortingEntity = new ProductSortingEntity();
        $productSortingEntity->setId(Uuid::randomHex());
        $productSortingEntity->setKey($sorting->getKey());
        $productSortingEntity->setPriority(0);
        $productSortingEntity->setActive($sorting->isActive());
        $productSortingEntity->setFields($fields);
        $productSortingEntity->setLabel($this->translator->trans($sorting->getSnippet()));
        $productSortingEntity->addTranslated('label', $this->translator->trans($sorting->getSnippet()));
        $productSortingEntity->setLocked(false);

        return $productSortingEntity;
    }
}
