<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

#[Package('inventory')]
class ProductKeywordDictionaryEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $keyword;

    /**
     * @var string
     */
    protected $reversed;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): void
    {
        $this->keyword = $keyword;
    }

    public function getReversed(): string
    {
        return $this->reversed;
    }

    public function setReversed(string $reversed): void
    {
        $this->reversed = $reversed;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
