<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * @internal
 */
#[Package('core')]
class ActionButtonTranslationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $label;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $appActionButtonId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $languageId;

    /**
     * @var ActionButtonEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $appActionButton;

    /**
     * @var LanguageEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $language;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getAppActionButtonId(): string
    {
        return $this->appActionButtonId;
    }

    public function setAppActionButtonId(string $appActionButtonId): void
    {
        $this->appActionButtonId = $appActionButtonId;
    }

    public function getAppActionButton(): ?ActionButtonEntity
    {
        return $this->appActionButton;
    }

    public function setAppActionButton(?ActionButtonEntity $appActionButton): void
    {
        $this->appActionButton = $appActionButton;
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
