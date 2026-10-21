<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells which MCP server a tool class is registered for, from the service tags in the compiled
 * container (`parameters.symfony.containerXmlPath`).
 *
 * The tag is the only thing the runtime uses to scope a tool: a Store API tool carries
 * `shopware.store_api_mcp.tool` next to the SDK tag `mcp.tool`, an Admin API tool only the SDK tag
 * (or `shopware.mcp.tool`). Nothing in the class itself is reliable: Store API tools may extend an
 * Admin API tool, need not extend McpToolResponse, and live in any namespace. A class the container
 * does not know, for example a test double or a project without a configured container, is
 * reported as unknown, and the MCP privilege rules skip it.
 *
 * @internal
 */
#[Package('framework')]
final class McpToolScopeResolver
{
    public const ADMIN_API = 'admin-api';

    public const STORE_API = 'store-api';

    public const UNKNOWN = 'unknown';

    private const MCP_TOOL_ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const STORE_API_TOOL_TAG = 'shopware.store_api_mcp.tool';

    private const ADMIN_API_TOOL_TAGS = ['mcp.tool', 'shopware.mcp.tool'];

    /**
     * @var array<string, array<string, true>>|null class => tag => true
     */
    private ?array $tagsByClass = null;

    public function __construct(private readonly ServiceMap $serviceMap)
    {
    }

    /**
     * @return self::ADMIN_API|self::STORE_API|self::UNKNOWN
     */
    public function resolve(string $className): string
    {
        $tags = $this->tagsByClass()[$className] ?? [];

        if (isset($tags[self::STORE_API_TOOL_TAG])) {
            return self::STORE_API;
        }

        foreach (self::ADMIN_API_TOOL_TAGS as $tag) {
            if (isset($tags[$tag])) {
                return self::ADMIN_API;
            }
        }

        return self::UNKNOWN;
    }

    /**
     * Whether the class declares `#[McpTool]`, on the class or on `__invoke()`, the two places the
     * SDK discoverer reads it from.
     */
    public static function isMcpTool(Class_ $class): bool
    {
        $invoke = $class->getMethod('__invoke');

        return self::attributesOf($class->attrGroups, self::MCP_TOOL_ATTRIBUTE) !== []
            || ($invoke !== null && self::attributesOf($invoke->attrGroups, self::MCP_TOOL_ATTRIBUTE) !== []);
    }

    /**
     * @param array<AttributeGroup> $attrGroups
     *
     * @return list<Attribute>
     */
    public static function attributesOf(array $attrGroups, string $attributeClass): array
    {
        $attributes = [];
        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($attribute->name->toString() === $attributeClass) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * Keyed by class rather than service id: a tool may be registered under an id that is not its
     * class name, and every definition of the class counts.
     *
     * @return array<string, array<string, true>>
     */
    private function tagsByClass(): array
    {
        if ($this->tagsByClass !== null) {
            return $this->tagsByClass;
        }

        $this->tagsByClass = [];
        foreach ($this->serviceMap->getServices() as $service) {
            if ($service->getAlias() !== null) {
                continue;
            }

            $class = ltrim($service->getClass() ?? $service->getId(), '\\');
            foreach ($service->getTags() as $tag) {
                /** @phpstan-ignore phpstanApi.method (ServiceTag is returned by the public ServiceDefinition API, but this accessor is not marked API) */
                $this->tagsByClass[$class][$tag->getName()] = true;
            }
        }

        return $this->tagsByClass;
    }
}
