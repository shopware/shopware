<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Symfony\ServiceMap;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells which MCP server a tool class is registered for, from the service tags in the compiled
 * container (`parameters.symfony.containerXmlPath`).
 *
 * The tag is the only thing the runtime uses to scope a tool: a Store API tool carries
 * `shopware.store_api_mcp.tool` next to the SDK tag `mcp.tool`, an Admin API tool only the SDK tag
 * (or `shopware.mcp.tool`). Nothing in the class itself is reliable: Store API tools may extend an
 * Admin API tool, need not extend McpToolResponse, and live in any namespace. Each service definition
 * is scoped on its own, so a class registered under an Admin API and a Store API service id gets both
 * scopes. A class the container does not know, for example a test double or a project without a
 * configured container, gets none, and the MCP privilege rules skip it.
 *
 * @internal
 */
#[Package('framework')]
final class McpToolScopeResolver
{
    public const ADMIN_API = 'admin-api';

    public const STORE_API = 'store-api';

    private const MCP_TOOL_ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const STORE_API_TOOL_TAG = 'shopware.store_api_mcp.tool';

    private const ADMIN_API_TOOL_TAGS = ['mcp.tool', 'shopware.mcp.tool'];

    /**
     * @var array<string, array<self::ADMIN_API|self::STORE_API, true>>|null class => scope => true
     */
    private ?array $scopesByClass = null;

    public function __construct(private readonly ServiceMap $serviceMap)
    {
    }

    /**
     * Whether any service definition of the class registers it on the given server.
     *
     * @param self::ADMIN_API|self::STORE_API $scope
     */
    public function hasScope(string $className, string $scope): bool
    {
        return isset($this->scopesByClass()[$className][$scope]);
    }

    /**
     * Whether the class declares `#[McpTool]`, on the class or on `__invoke()`, the two places the
     * SDK discoverer reads it from. Resolved through reflection like McpToolAttributeReader does at
     * runtime, so an `__invoke()` inherited from a parent class or a trait counts as well.
     */
    public static function isMcpTool(ClassReflection $class): bool
    {
        $native = $class->getNativeReflection();
        if ($native->getAttributes(self::MCP_TOOL_ATTRIBUTE) !== []) {
            return true;
        }

        return $native->hasMethod('__invoke')
            && $native->getMethod('__invoke')->getAttributes(self::MCP_TOOL_ATTRIBUTE) !== [];
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
     * class name, and every definition of the class counts. A definition with the Store API tag is a
     * Store API tool even though it also carries the SDK tag `mcp.tool`.
     *
     * @return array<string, array<self::ADMIN_API|self::STORE_API, true>>
     */
    private function scopesByClass(): array
    {
        if ($this->scopesByClass !== null) {
            return $this->scopesByClass;
        }

        $this->scopesByClass = [];
        foreach ($this->serviceMap->getServices() as $service) {
            if ($service->getAlias() !== null) {
                continue;
            }

            $tags = [];
            foreach ($service->getTags() as $tag) {
                /** @phpstan-ignore phpstanApi.method (ServiceTag is returned by the public ServiceDefinition API, but this accessor is not marked API) */
                $tags[$tag->getName()] = true;
            }

            $scope = match (true) {
                isset($tags[self::STORE_API_TOOL_TAG]) => self::STORE_API,
                array_intersect_key($tags, array_flip(self::ADMIN_API_TOOL_TAGS)) !== [] => self::ADMIN_API,
                default => null,
            };

            if ($scope !== null) {
                $this->scopesByClass[ltrim($service->getClass() ?? $service->getId(), '\\')][$scope] = true;
            }
        }

        return $this->scopesByClass;
    }
}
