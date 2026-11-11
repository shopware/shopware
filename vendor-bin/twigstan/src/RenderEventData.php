<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-import-type Listener from RenderEventListeners
 * @phpstan-import-type Write from RenderEventParameterCollector
 *
 * @internal
 */
#[Package('framework')]
final class RenderEventData
{
    /**
     * @var array<string, list<Write>>
     */
    private array $writes = [];

    /**
     * @param list<Listener> $listeners
     * @param list<Write> $writes
     */
    public function __construct(private readonly TypeStringResolver $resolver, private array $listeners, array $writes, private readonly ?string $sourceFile = null)
    {
        usort($this->listeners, static fn (array $left, array $right): int => [-$left['priority'], $left['order']] <=> [-$right['priority'], $right['order']]);
        foreach ($writes as $write) {
            $this->writes[strtolower($write['callback'])][] = $write;
        }
    }

    public static function fromFile(TypeStringResolver $resolver, string $file): self
    {
        /** @var array{listeners: list<Listener>, writes: list<Write>} $data */
        $data = json_decode((new Filesystem())->readFile($file), true, flags: \JSON_THROW_ON_ERROR);

        return new self($resolver, $data['listeners'], $data['writes'], $file);
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function applyToRender(Type $context, string $template, Scope $scope): Type
    {
        if ($this->sourceFile !== null) {
            $scope->trackFileDependency($this->sourceFile);
        }
        $class = $scope->getClassReflection();
        $method = $scope->getFunctionName();
        $scopes = [];
        $prefix = '';
        if ($class !== null && $method !== null && $class->hasNativeMethod($method)) {
            foreach ($this->routes($class->getAttributes()) as [$name, $routeScopes]) {
                $prefix = $name ?? '';
                $scopes = $routeScopes ?? [];
            }
            $contexts = [];
            foreach ($this->routes($class->getNativeMethod($method)->getAttributes()) as [$name, $routeScopes]) {
                $contexts[] = $this->apply($context, $template, $name !== null ? [$prefix . $name] : [], $routeScopes ?? $scopes);
            }
            if ($contexts !== []) {
                return TypeCombinator::union(...$contexts);
            }
        }

        return $this->apply($context, $template, [], $scopes);
    }

    /**
     * @param list<string> $routes
     * @param list<string> $scopes
     */
    public function apply(Type $context, string $template, array $routes = [], array $scopes = []): Type
    {
        $contexts = [];
        // Each controller route is an alternative request, rather than another dispatch on the same event.
        foreach ($routes !== [] ? $routes : [null] as $route) {
            $pipeline = [];
            foreach ($this->listeners as $listener) {
                if ($listener['event'] !== StorefrontRenderEvent::class) {
                    continue;
                }
                if ($listener['callback'] === StorefrontRouteEventSubscriber::class . '::render') {
                    foreach (array_merge($route !== null ? [$route . '.render'] : [], array_map(static fn (string $scope): string => $scope . '.scope.render', $scopes)) as $event) {
                        foreach ($this->listeners as $nested) {
                            if ($nested['event'] === $event) {
                                $pipeline[] = $nested;
                            }
                        }
                    }
                } else {
                    $pipeline[] = $listener;
                }
            }

            $current = $context;
            $mayStop = false;
            foreach ($pipeline as $listener) {
                $stops = false;
                $mayStopAfter = false;
                foreach ($this->writes[strtolower($listener['callback'])] ?? [] as $write) {
                    if ($write['views'] !== [] && !\in_array($template, $write['views'], true)) {
                        continue;
                    }
                    if ($write['kind'] === 'stop') {
                        $stops = $stops || !$write['optional'];
                        $mayStopAfter = true;
                    } elseif ($write['kind'] === 'parameter') {
                        $current = $this->write($current, $write['key'], $this->resolver->resolve($write['type']), $write['optional'] || $mayStop);
                    }
                }
                if ($stops) {
                    break;
                }
                $mayStop = $mayStop || $mayStopAfter;
            }
            $contexts[] = $current;
        }

        return TypeCombinator::union(...$contexts);
    }

    /**
     * @param list<AttributeReflection> $attributes
     *
     * @return list<array{string|null, list<string>|null}>
     */
    private function routes(array $attributes): array
    {
        $routes = [];
        foreach ($attributes as $attribute) {
            if (!\in_array($attribute->getName(), [Route::class, 'Symfony\\Component\\Routing\\Annotation\\Route'], true)) {
                continue;
            }
            $arguments = $attribute->getArgumentTypes();
            $names = isset($arguments['name']) ? $arguments['name']->getConstantStrings() : [];
            $defaults = $arguments['defaults'] ?? null;
            $key = new ConstantStringType(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);
            $scopes = null;
            if ($defaults !== null && $defaults->hasOffsetValueType($key)->yes()) {
                $scopes = [];
                foreach ($defaults->getOffsetValueType($key)->getConstantArrays() as $array) {
                    foreach ($array->getValueTypes() as $value) {
                        foreach ($value->getConstantStrings() as $scope) {
                            $scopes[] = $scope->getValue();
                        }
                    }
                }
            }
            $routes[] = [$names !== [] ? $names[0]->getValue() : null, $scopes];
        }

        return $routes;
    }

    private function write(Type $context, string $key, Type $value, bool $optional): Type
    {
        $offset = new ConstantStringType($key);
        if (!$optional) {
            return $context->setOffsetValueType($offset, $value);
        }
        if (!$context->isConstantArray()->yes()) {
            return TypeCombinator::union($context, $context->setOffsetValueType($offset, $value));
        }

        $contexts = [];
        foreach ($context->getConstantArrays() as $array) {
            $hasKey = $array->hasOffsetValueType($offset);
            $type = $hasKey->no() ? $value : TypeCombinator::union($array->getOffsetValueType($offset), $value);
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($array);
            $builder->setOffsetValueType($offset, $type, !$hasKey->yes());
            $contexts[] = $builder->getArray();
        }

        return TypeCombinator::union(...$contexts);
    }
}
