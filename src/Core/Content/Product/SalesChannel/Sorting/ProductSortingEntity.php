<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductSortingEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var ProductSortingTranslationCollection|null
     */
    protected $translations;

    /**
     * @var bool
     */
    protected $locked;

    public function createDalSorting(): array
    {
        $sorting = [];

        $fields = $this->fields;

        usort($fields, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($fields as $field) {
            $direction = mb_strtoupper((string) $field['order']) === FieldSorting::ASCENDING
                ? FieldSorting::ASCENDING
                : FieldSorting::DESCENDING;

            $sorting[] = new FieldSorting(
                $field['field'],
                $direction,
                (bool) ($field['naturalSorting'] ?? false)
            );
        }

        return $sorting;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTranslations(): ?ProductSortingTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductSortingTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function getApiAlias(): string
    {
        return 'product_sorting';
    }
}
