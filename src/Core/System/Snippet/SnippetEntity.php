<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

#[Package('system-settings')]
class SnippetEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $setId;

    /**
     * @var string
     */
    protected $translationKey;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var SnippetSetEntity|null
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

    public function setValue(?string $value): void
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
