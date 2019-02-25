<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class DocumentTypeTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
