<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class ContentRouteDefinition
{
    /**
     * @param array<string, string> $requirements
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        public string $path,
        public string $name,
        public array $requirements,
        public array $defaults,
    ) {
    }

    public function toDefinition(): Definition
    {
        return new Definition(self::class, [
            $this->path,
            $this->name,
            $this->requirements,
            $this->defaults,
        ]);
    }
}
