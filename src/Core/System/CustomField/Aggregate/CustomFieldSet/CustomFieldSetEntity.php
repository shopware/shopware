<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSet;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;

class CustomFieldSetEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $global;

    /**
     * @var CustomFieldCollection|null
     */
    protected $customFields;

    /**
     * @var CustomFieldSetRelationCollection|null
     */
    protected $relations;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCustomFields(): ?CustomFieldCollection
    {
        return $this->customFields;
    }

    public function setCustomFields(CustomFieldCollection $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getRelations(): ?CustomFieldSetRelationCollection
    {
        return $this->relations;
    }

    public function setRelations(CustomFieldSetRelationCollection $relations): void
    {
        $this->relations = $relations;
    }

    public function getApiAlias(): string
    {
        return 'custom_field_set';
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }
}
