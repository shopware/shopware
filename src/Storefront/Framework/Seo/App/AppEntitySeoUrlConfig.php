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
final readonly class AppEntitySeoUrlConfig implements AppFeatureConfig
{
    public function __construct(
        private string $name,
        private string $routeName,
        private string $hook,
        private string $entityName,
        private string $defaultTemplate,
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

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getDefaultTemplate(): string
    {
        return $this->defaultTemplate;
    }
}
