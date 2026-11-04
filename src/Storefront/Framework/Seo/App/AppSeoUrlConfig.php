<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('inventory')]
final readonly class AppSeoUrlConfig implements AppFeatureConfig
{
    /**
     * @param array<string, string> $paths locale code => path
     */
    public function __construct(
        private string $name,
        private string $routeName,
        private string $hook,
        private array $paths,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getHook(): string
    {
        return $this->hook;
    }

    /**
     * @return array<string, string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }
}
