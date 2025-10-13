<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Components;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\UX\TwigComponent\Event\PostRenderEvent;

#[Package('framework')]
#[AsEventListener]
class TwigComponentRenderEventListener
{
    /** @var array<string> */
    private array $mountedComponents = [];

    /** @internal  */
    public function __construct() {}

    public function __invoke(PostRenderEvent $event): void
    {
        // Track which components will be rendered.
        $this->trackComponentUsage($event);
    }

    /**
     * Track component usage for this request
     */
    private function trackComponentUsage(PostRenderEvent $event): void
    {
        $mountedComponent = $event->getMountedComponent();
        $componentName = $mountedComponent->getName();

        if ($componentName) {
            // Store in a static property that can be accessed.
            if (!in_array($componentName, $this->mountedComponents, true)) {
                $this->mountedComponents[] = $componentName;
            }
        }
    }

    /**
     * Get all components that were rendered in this request
     * 
     * @return array<string>
     */
    public function getMountedComponents(): array
    {
        return $this->mountedComponents;
    }

    /**
     * Get count of rendered components
     */
    public function getMountedComponentsCount(): int
    {
        return count($this->mountedComponents);
    }

    /**
     * Reset the component tracking (useful for testing)
     */
    public function resetComponentTracking(): void
    {
        $this->mountedComponents = [];
    }
}
