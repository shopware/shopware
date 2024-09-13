<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

#[Package('services-settings')]
class SnippetEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - will be changed to native type
     */
    protected $setId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - will be changed to native type
     */
    protected $translationKey;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - will be changed to native type
     */
    protected $value;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - will be changed to native type
     */
    protected $author;

    /**
     * @var SnippetSetEntity|null
     *
     * @deprecated tag:v6.7.0 - will be changed to native type
     */
    protected $set;

    public function getSetId(): string
    {
        return $this->setId;
    }

    public function setSetId(string $setId): void
    {
        $this->setId = $setId;
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function setTranslationKey(string $translationKey): void
    {
        $this->translationKey = $translationKey;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getSet(): ?SnippetSetEntity
    {
        return $this->set;
    }

    public function setSet(SnippetSetEntity $set): void
    {
        $this->set = $set;
    }
}
