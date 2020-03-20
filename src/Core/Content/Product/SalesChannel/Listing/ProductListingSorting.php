<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Struct;

class ProductListingSorting extends Struct
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $snippet;

    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string[]
     */
    private $fields;

    public function __construct(string $key, string $snippet, array $fields)
    {
        $this->key = $key;
        $this->snippet = $snippet;
        $this->fields = $fields;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function setSnippet(string $snippet): void
    {
        $this->snippet = $snippet;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function createDalSortings(): array
    {
        $sortings = [];
        foreach ($this->fields as $field => $direction) {
            if (mb_strtoupper($direction) === FieldSorting::ASCENDING) {
                $sortings[] = new FieldSorting($field, FieldSorting::ASCENDING);
            } else {
                $sortings[] = new FieldSorting($field, FieldSorting::DESCENDING);
            }
        }

        return $sortings;
    }

    public function getApiAlias(): string
    {
        return 'product_listing_sorting';
    }
}
