<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\CmsBlock;

use Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('content')]
class AppCmsBlockEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $appId;

    /**
     * @var AppEntity
     */
    protected $app;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $block;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $styles;

    /**
     * @var AppCmsBlockTranslationCollection
     */
    protected $translations;

    protected ?string $label = null;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): AppEntity
    {
        return $this->app;
    }

    public function setApp(AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBlock(): array
    {
        return $this->block;
    }

    public function setBlock(array $block): void
    {
        $this->block = $block;
    }

    public function getTranslations(): AppCmsBlockTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppCmsBlockTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getStyles(): string
    {
        return $this->styles;
    }

    public function setStyles(string $styles): void
    {
        $this->styles = $styles;
    }
}
