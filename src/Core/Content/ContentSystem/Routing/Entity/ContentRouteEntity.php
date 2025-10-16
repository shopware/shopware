<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Entity;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentCollection;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ParameterBinding;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouteEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    /**
     * Normalized during persistence to ensure leading slash.
     */
    protected string $urlPattern;

    /**
     * @var array<string, ParameterBinding>
     */
    protected array $parameterBindings;

    protected int $priority = 0;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $overrides = null;

    protected bool $active = true;

    protected ?ContentLayoutAssignmentCollection $layoutAssignments = null;

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
     * @return array<string, ParameterBinding>
     */
    public function getParameterBindings(): array
    {
        return $this->parameterBindings;
    }

    /**
     * @param array<string, ParameterBinding> $parameterBindings
     */
    public function setParameterBindings(array $parameterBindings): void
    {
        $this->parameterBindings = $parameterBindings;
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

    public function getLayoutAssignments(): ?ContentLayoutAssignmentCollection
    {
        return $this->layoutAssignments;
    }

    public function setLayoutAssignments(?ContentLayoutAssignmentCollection $layoutAssignments): void
    {
        $this->layoutAssignments = $layoutAssignments;
    }
}
