<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * @internal
 */
#[Package('content')]
class AppCmsBlockTranslationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $appCmsBlockId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var AppCmsBlockEntity|null
     */
    protected $appCmsBlock;

    /**
     * @var LanguageEntity|null
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

    public function getAppCmsBlockId(): string
    {
        return $this->appCmsBlockId;
    }

    public function setAppCmsBlockId(string $appCmsBlockId): void
    {
        $this->appCmsBlockId = $appCmsBlockId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getAppCmsBlock(): ?AppCmsBlockEntity
    {
        return $this->appCmsBlock;
    }

    public function setAppCmsBlock(?AppCmsBlockEntity $appCmsBlock): void
    {
        $this->appCmsBlock = $appCmsBlock;
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
