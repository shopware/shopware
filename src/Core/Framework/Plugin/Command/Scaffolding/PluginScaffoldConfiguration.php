<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class PluginScaffoldConfiguration
{
    public const ROUTE_XML_OPTION_NAME = 'create-route-xml';

    /**
     * @param array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly string $directory,
        public array $options = []
    ) {
    }

    public function addOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    public function getOption(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }
}
