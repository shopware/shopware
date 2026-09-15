<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppSeoUrlRouteEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $routeName;

    protected string $hook;

    protected ?string $entityName = null;

    protected ?string $defaultTemplate = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $paths = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $label = null;

    protected string $appId;

    protected ?AppEntity $app = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getHook(): string
    {
        return $this->hook;
    }

    public function setHook(string $hook): void
    {
        $this->hook = $hook;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getDefaultTemplate(): ?string
    {
        return $this->defaultTemplate;
    }

    public function setDefaultTemplate(?string $defaultTemplate): void
    {
        $this->defaultTemplate = $defaultTemplate;
    }

    /**
     * @return array<string, string>|null
     */
    public function getPaths(): ?array
    {
        return $this->paths;
    }

    /**
     * @param array<string, string>|null $paths
     */
    public function setPaths(?array $paths): void
    {
        $this->paths = $paths;
    }

    /**
     * @return array<string, string>|null
     */
    public function getLabel(): ?array
    {
        return $this->label;
    }

    /**
     * @param array<string, string>|null $label
     */
    public function setLabel(?array $label): void
    {
        $this->label = $label;
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
}
