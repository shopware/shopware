<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build when a tagged property projection cannot answer its contract: the class must extend
 * AbstractContentPropertyProjection, its name must be unique across the container, and its declared input and
 * output types must each be an existing class or a `PropertyType::PRIMITIVE_TYPES` member.
 *
 * Both checks guard something that has no second line of defence. A duplicate name silently changes what every
 * layout mapping through it renders, decided by service registration order. An unresolvable type makes the
 * render path's input gate vacuous, so a mismatched value reaches `project()` and fails there as a TypeError
 * mid-render instead of as a readable build failure.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemPropertyProjectionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $namedBy = [];

        foreach (array_keys($container->findTaggedServiceIds('content_system.property_projection')) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();

            if ($class === null || !class_exists($class)) {
                // No resolvable class to introspect; leave it for Symfony's own service validation.
                continue;
            }

            if ($definition->isAbstract() || (new \ReflectionClass($class))->isAbstract()) {
                // A parent template rather than a service, so there is no projection here to hold to the contract.
                continue;
            }

            if (!is_subclass_of($class, AbstractContentPropertyProjection::class)) {
                throw DependencyInjectionException::taggedServiceHasWrongType($serviceId, 'content_system.property_projection', AbstractContentPropertyProjection::class);
            }

            // name(), inputType() and outputType() are instance methods but declare constants, so they are
            // dry-run here on an instance built without its constructor, at build time and without the
            // container — the same way ContentSystemDataLoaderCompilerPass dry-runs configSpecification().
            $projection = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

            $this->validateType($class, 'input', $projection->inputType());
            $this->validateType($class, 'output', $projection->outputType());

            $name = $projection->name();
            $priorProjection = $namedBy[$name] ?? null;

            if ($priorProjection !== null) {
                throw DependencyInjectionException::propertyProjectionDuplicateName($class, $priorProjection, $name);
            }

            $namedBy[$name] = $class;
        }
    }

    private function validateType(string $class, string $side, string $type): void
    {
        if (\in_array($type, PropertyType::PRIMITIVE_TYPES, true) || class_exists($type) || interface_exists($type)) {
            return;
        }

        throw DependencyInjectionException::propertyProjectionInvalidType($class, $side, $type, PropertyType::PRIMITIVE_TYPES);
    }
}
