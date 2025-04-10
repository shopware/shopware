<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\ActionButton;

use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ActionButtonEntity extends Entity
{
    use EntityIdTrait;

    protected string $action;

    protected ?string $label = null;

    protected string $entity;

    protected string $view;

    protected string $url;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected ?ActionButtonTranslationCollection $translations = null;

    public function getAction(): string
    {
        return $this->action;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getTranslations(): ?ActionButtonTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ActionButtonTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
