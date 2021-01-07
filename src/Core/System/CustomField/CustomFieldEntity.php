<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;

class CustomFieldEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string|null
     */
    protected $customFieldSetId;

    /**
     * @var CustomFieldSetEntity|null
     */
    protected $customFieldSet;

    /**
     * @internal (FEATURE_NEXT_10552)
     *
     * @var ProductSearchConfigFieldCollection |null
     */
    protected $productSearchConfigFields;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
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

    public function getCustomFieldSetId(): ?string
    {
        return $this->customFieldSetId;
    }

    public function setCustomFieldSetId(?string $attributeSetId): void
    {
        $this->customFieldSetId = $attributeSetId;
    }

    public function getCustomFieldSet(): ?CustomFieldSetEntity
    {
        return $this->customFieldSet;
    }

    public function setCustomFieldSet(?CustomFieldSetEntity $attributeSet): void
    {
        $this->customFieldSet = $attributeSet;
    }

    /**
     * @internal (FEATURE_NEXT_10552)
     */
    public function getProductSearchConfigFields(): ?ProductSearchConfigFieldCollection
    {
        return $this->productSearchConfigFields;
    }

    /**
     * @internal (FEATURE_NEXT_10552)
     */
    public function setProductSearchConfigFields(ProductSearchConfigFieldCollection $productSearchConfigFields): void
    {
        $this->productSearchConfigFields = $productSearchConfigFields;
    }
}
