<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Struct;

use Shopware\Core\Framework\ORM\Entity;

class ContextCartModifierTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $contextCartModifierId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getContextCartModifierId(): string
    {
        return $this->contextCartModifierId;
    }

    public function setContextCartModifierId(string $contextCartModifierId): void
    {
        $this->contextCartModifier = $contextCartModifierId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
