<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event;

use Shopware\Core\Framework\Api\ApiDefinition\Generator\PluginSchemaPathCollection;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Will be removed
 * Create a src/Resources/Schema/ in a plugin to extend the open api schema
 * @see PluginSchemaPathCollection
 */
class OpenApiPathsEvent extends Event
{
    /**
     * @var array<string>
     */
    private $paths;

    /**
     * @param array<string> $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function addPath(string $path): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );
        $this->paths[] = $path;
    }

    /**
     * @return array<string>
     */
    public function getPaths(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->paths;
    }

    public function isEmpty(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return empty($this->paths);
    }
}
