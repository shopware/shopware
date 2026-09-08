<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\BCChangeMarkers;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Demodata\Command\DemodataCommand;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class InternalClassRule implements Rule
{
    private const INTERNAL_NAMESPACES = [
        '\\DevOps\\StaticAnalyze',
        '\\Core\\Maintenance',
        '\\ContentSystem\\',
    ];
    private const SUBSCRIBER_EXCEPTIONS = [
        RefreshIndexCommand::class,
    ];
    private const MESSAGE_HANDLER_EXCEPTIONS = [
        EntityIndexerRegistry::class,
    ];
    private const DEMO_DATA_EXCEPTIONS = [
        DemodataContext::class,
        DemodataGeneratorInterface::class,
        DemodataRequest::class,
        DemodataService::class,
        DemodataCommand::class,
        DemodataRequestCreatedEvent::class,
    ];
    /**
     * The content-system module's public extension surface: every class a plugin must reach to use it.
     * These carry no `@internal`; every other class in the module still must. Held as plain strings so
     * this rule takes on no dependency on the module's namespace.
     */
    private const CONTENT_SYSTEM_PUBLIC_SURFACE = [
        'Shopware\\Core\\Framework\\ContentSystem\\Event\\ContentTreePreparationEvent',
        'Shopware\\Core\\Framework\\ContentSystem\\Event\\RenderedTreeFinalizationEvent',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoader',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoaderConfigSerializer',
        'Shopware\\Core\\Framework\\ContentSystem\\Output\\Format\\AbstractResponseFactory',
        'Shopware\\Core\\Framework\\ContentSystem\\Adapter\\AbstractSpecificationSource',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Loader\\AbstractContentSystemElementTypeLoader',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Loader\\AbstractContentSystemStyleOptionLoader',
        'Shopware\\Core\\Framework\\ContentSystem\\Binding\\Loader\\AbstractContentSystemBindingSpecificationLoader',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\StoredElement',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\StoredValue',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\StoredTree',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextDefinitions',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextProvider',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ContextConsumer',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\ConsumerScope',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataContext\\ContextType',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\Distribution\\DistributionConfig',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Context\\Distribution\\DistributionStrategy',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\DataRequirement\\DataRequirement',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\ElementStyle',
        'Shopware\\Core\\Framework\\ContentSystem\\Rendering\\RenderedElement',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\RenderedTreeEditor',
        'Shopware\\Core\\Framework\\ContentSystem\\Cache\\RenderingCacheContext',
        'Shopware\\Core\\Framework\\ContentSystem\\LayoutReference',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Entity\\ContentLayoutEntity',
        'Shopware\\Core\\Framework\\ContentSystem\\RenderableLayout',
        'Shopware\\Core\\Framework\\ContentSystem\\ResolvedContentLayout',
        'Shopware\\Core\\Framework\\ContentSystem\\ContentSection',
        'Shopware\\Core\\Framework\\ContentSystem\\RenderingSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\PlaceholderValues',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\AbstractContentDataLoaderConfig',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderInputs',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ContentDataLoaderResult',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderConfigSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ConfigKeySpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\ConfigKeyKind',
        'Shopware\\Core\\Framework\\ContentSystem\\Hydration\\DataLoader\\LoaderTypeCapability',
        'Shopware\\Core\\Framework\\ContentSystem\\Output\\RenderResult',
        'Shopware\\Core\\Framework\\ContentSystem\\Output\\Index\\ResolvedValueIndex',
        'Shopware\\Core\\Framework\\ContentSystem\\RenderingMode',
        'Shopware\\Core\\Framework\\ContentSystem\\SalesChannel\\AbstractContentRouteResponse',
        'Shopware\\Core\\Framework\\ContentSystem\\Resolution\\ProvidedContext',
        'Shopware\\Core\\Framework\\ContentSystem\\SpecificationData',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\ContentSystemElementTypeSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\PropertySpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\PropertyType',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\SlotSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Type\\Specification\\CopilotSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Specification\\StyleOptionSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Layout\\Element\\Style\\Specification\\StyleOptionValueType',
        'Shopware\\Core\\Framework\\ContentSystem\\Binding\\Specification\\BindingSpecification',
        'Shopware\\Core\\Framework\\ContentSystem\\Binding\\Specification\\LoaderBinding',
        'Shopware\\Core\\Framework\\ContentSystem\\Binding\\Specification\\BindingInput',
        'Shopware\\Core\\Framework\\ContentSystem\\ContentSystemException',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $doc = $node->getDocComment()?->getText() ?? '';

        if ($this->isInternal($doc, $node->getClassReflection())) {
            return [];
        }

        $class = $node->getClassReflection()->getName();

        if ($this->isExample($node)) {
            return [];
        }
        if ($this->isTestClass($node)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Test classes (%s) must be flagged @internal to not be captured by the BC checker',
                    $node->getClassReflection()->getName()
                ))
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isStorefrontController($node)) {
            return [
                RuleErrorBuilder::message('Storefront controllers must be flagged @internal to not be captured by the BC checker. The BC promise is checked over the route annotation.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isBundle($node)) {
            return [
                RuleErrorBuilder::message('Bundles must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isEventSubscriber($node) && !$this->isFinal($node->getClassReflection(), $doc) && !\in_array($class, self::SUBSCRIBER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('Event subscribers must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if (($namespace = $this->isInInternalNamespace($node)) && !\in_array($class, self::CONTENT_SYSTEM_PUBLIC_SURFACE, true)) {
            return [
                RuleErrorBuilder::message('Classes in `' . $namespace . '` namespace must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isInNamespace($node, '\\Framework\\Demodata') && !\in_array($class, self::DEMO_DATA_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('Classes in `Framework\\Demodata` namespace must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMigrationStep($node)) {
            return [
                RuleErrorBuilder::message('Migrations must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMessageHandler($node) && !\in_array($class, self::MESSAGE_HANDLER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('MessageHandlers must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        if ($this->isParentInternalAndAbstract($scope) && !$this->isFinal($node->getClassReflection(), $doc)) {
            return [
                RuleErrorBuilder::message('Classes that extend an @internal abstract class must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('shopware.internalClass')
                    ->build(),
            ];
        }

        return [];
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, 'Shopware\\Core\\Test\\Stub\\')) {
            return false;
        }

        if (\str_contains($namespace, '\\Test\\Integration\\Builder\\')) {
            // Test builder classes are not internal by design
            return false;
        }

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function isInternal(string $doc, ClassReflection $class): bool
    {
        return \str_contains($doc, '@internal')
            || BCChangeMarkers::has(BecomesInternal::class, $class);
    }

    private function isStorefrontController(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        /** @phpstan-ignore phpat.restrictNamespacesInCore (only class constant is used) */
        return $class->getParentClass()->getName() === StorefrontController::class;
    }

    private function isBundle(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        if ($class->isAnonymous()) {
            return false;
        }

        return $class->getParentClass()->getName() === Bundle::class && $class->getName() !== Plugin::class;
    }

    private function isEventSubscriber(InClassNode $node): bool
    {
        foreach ($node->getClassReflection()->getInterfaces() as $interface) {
            if ($interface->getName() === EventSubscriberInterface::class) {
                return true;
            }
        }

        return false;
    }

    private function isInInternalNamespace(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, 'Shopware\\Core\\Test\\Stub\\')) {
            // Test stub classes are public builders by design, as isTestClass() already encodes.
            // This exempts them from the namespace requirement only; a stub extending an
            // @internal abstract still has to be @internal or @final.
            return null;
        }

        foreach (self::INTERNAL_NAMESPACES as $internalNamespace) {
            if (\str_contains($namespace, $internalNamespace)) {
                return $internalNamespace;
            }
        }

        return null;
    }

    private function isInNamespace(InClassNode $node, string $namespace): bool
    {
        return \str_contains($node->getClassReflection()->getName(), $namespace);
    }

    private function isMigrationStep(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        return $class->getParentClass()->getName() === MigrationStep::class;
    }

    private function isMessageHandler(InClassNode $node): bool
    {
        $class = $node->getClassReflection()->getNativeReflection();

        if ($class->isAbstract()) {
            // abstract base classes should not be final
            return false;
        }

        return $class->getAttributes(AsMessageHandler::class) !== [];
    }

    private function isFinal(ClassReflection $class, string $doc): bool
    {
        return str_contains($doc, '@final')
            || $class->isFinal()
            || BCChangeMarkers::has(BecomesFinal::class, $class);
    }

    private function isParentInternalAndAbstract(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        \assert($class !== null);
        $parent = $class->getParentClass();

        if ($parent === null) {
            return false;
        }

        if (!$parent->isAbstract()) {
            return false;
        }

        $native = $parent->getNativeReflection();

        $doc = $native->getDocComment() ?: '';

        return $this->isInternal($doc, $parent);
    }

    private function isExample(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        return \str_contains($namespace, 'Shopware\\Tests\\Examples\\');
    }
}
