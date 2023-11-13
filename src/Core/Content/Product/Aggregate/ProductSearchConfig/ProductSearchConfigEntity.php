<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

#[Package('inventory')]
class ProductSearchConfigEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var bool
     */
    protected $andLogic;

    /**
     * @var int
     */
    protected $minSearchLength;

    /**
     * @var array|null
     */
    protected $excludedTerms;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var ProductSearchConfigFieldCollection|null
     */
    protected $configFields;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getAndLogic(): bool
    {
        return $this->andLogic;
    }

    public function setAndLogic(bool $andLogic): void
    {
        $this->andLogic = $andLogic;
    }

    public function getMinSearchLength(): int
    {
        return $this->minSearchLength;
    }

    public function setMinSearchLength(int $minSearchLength): void
    {
        $this->minSearchLength = $minSearchLength;
    }

    public function getExcludedTerms(): ?array
    {
        return $this->excludedTerms;
    }

    public function setExcludedTerms(?array $excludedTerms): void
    {
        $this->excludedTerms = $excludedTerms;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getConfigFields(): ?ProductSearchConfigFieldCollection
    {
        return $this->configFields;
    }

    public function setConfigFields(ProductSearchConfigFieldCollection $configFields): void
    {
        $this->configFields = $configFields;
    }

    public function getApiAlias(): string
    {
        return 'product_search_config';
    }
}
