<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Entity;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouteEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $urlPattern;

    /**
     * @var array<string, mixed>
     */
    protected array $parameterBinding;

    protected ?string $layoutId = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $layoutCascade = null;

    protected int $priority = 0;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $overrides = null;

    protected bool $active = true;

    protected ?ContentLayoutEntity $layout = null;

    protected ?SalesChannelCollection $salesChannels = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUrlPattern(): string
    {
        return $this->urlPattern;
    }

    public function setUrlPattern(string $urlPattern): void
    {
        $this->urlPattern = $urlPattern;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameterBinding(): array
    {
        return $this->parameterBinding;
    }

    /**
     * @param array<string, mixed> $parameterBinding
     */
    public function setParameterBinding(array $parameterBinding): void
    {
        $this->parameterBinding = $parameterBinding;
    }

    public function getLayoutId(): ?string
    {
        return $this->layoutId;
    }

    public function setLayoutId(?string $layoutId): void
    {
        $this->layoutId = $layoutId;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getLayoutCascade(): ?array
    {
        return $this->layoutCascade;
    }

    /**
     * @param array<int, array<string, mixed>>|null $layoutCascade
     */
    public function setLayoutCascade(?array $layoutCascade): void
    {
        $this->layoutCascade = $layoutCascade;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOverrides(): ?array
    {
        return $this->overrides;
    }

    /**
     * @param array<string, mixed>|null $overrides
     */
    public function setOverrides(?array $overrides): void
    {
        $this->overrides = $overrides;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLayout(): ?ContentLayoutEntity
    {
        return $this->layout;
    }

    public function setLayout(?ContentLayoutEntity $layout): void
    {
        $this->layout = $layout;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(?SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
