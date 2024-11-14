<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldEntity;

#[Package('inventory')]
class ProductSearchConfigFieldEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchConfigId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customFieldId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $field;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $tokenize;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchable;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $ranking;

    /**
     * @var ProductSearchConfigEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchConfig;

    /**
     * @var CustomFieldEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $customField;

    public function getSearchConfigId(): string
    {
        return $this->searchConfigId;
    }

    public function setSearchConfigId(string $searchConfigId): void
    {
        $this->searchConfigId = $searchConfigId;
    }

    public function getCustomFieldId(): ?string
    {
        return $this->customFieldId;
    }

    public function setCustomFieldId(?string $customFieldId): void
    {
        $this->customFieldId = $customFieldId;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getTokenize(): bool
    {
        return $this->tokenize;
    }

    public function setTokenize(bool $tokenize): void
    {
        $this->tokenize = $tokenize;
    }

    public function getSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable): void
    {
        $this->searchable = $searchable;
    }

    public function getRanking(): int
    {
        return $this->ranking;
    }

    public function setRanking(int $ranking): void
    {
        $this->ranking = $ranking;
    }

    public function getSearchConfig(): ?ProductSearchConfigEntity
    {
        return $this->searchConfig;
    }

    public function setSearchConfig(ProductSearchConfigEntity $searchConfig): void
    {
        $this->searchConfig = $searchConfig;
    }

    public function getCustomField(): ?CustomFieldEntity
    {
        return $this->customField;
    }

    public function setCustomField(?CustomFieldEntity $customField): void
    {
        $this->customField = $customField;
    }

    public function getApiAlias(): string
    {
        return 'product_search_config_field';
    }
}
