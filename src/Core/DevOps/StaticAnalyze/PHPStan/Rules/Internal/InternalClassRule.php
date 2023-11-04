<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Demodata\Command\DemodataCommand;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute\StoreApiTestOtherRoute;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class InternalClassRule implements Rule
{
    private const TEST_CLASS_EXCEPTIONS = [
        StoreApiTestOtherRoute::class, // The test route is used to test the OpenApiGenerator, that class would ignore internal classes
    ];

    private const INTERNAL_NAMESPACES = [
        '\\DevOps\\StaticAnalyze',
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

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

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

        if ($this->isInternal($doc)) {
            return [];
        }

        $class = $node->getClassReflection()->getName();

        if ($this->isTestClass($node)) {
            return [\sprintf('Test classes (%s) must be flagged @internal to not be captured by the BC checker', $node->getClassReflection()->getName())];
        }

        if ($this->isStorefrontController($node)) {
            return ['Storefront controllers must be flagged @internal to not be captured by the BC checker. The BC promise is checked over the route annotation.'];
        }

        if ($this->isBundle($node)) {
            return ['Bundles must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isEventSubscriber($node) && !$this->isFinal($node->getClassReflection(), $doc) && !\in_array($class, self::SUBSCRIBER_EXCEPTIONS, true)) {
            return ['Event subscribers must be flagged @internal or @final to not be captured by the BC checker.'];
        }

        if ($namespace = $this->isInInternalNamespace($node)) {
            return ['Classes in `' . $namespace . '` namespace must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isInNamespace($node, '\\Framework\\Demodata') && !\in_array($class, self::DEMO_DATA_EXCEPTIONS, true)) {
            return ['Classes in `Framework\\Demodata` namespace must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isMigrationStep($node)) {
            return ['Migrations must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isMessageHandler($node) && !\in_array($class, self::MESSAGE_HANDLER_EXCEPTIONS, true)) {
            return ['MessageHandlers must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isParentInternalAndAbstract($scope) && !$this->isFinal($node->getClassReflection(), $doc)) {
            return ['Classes that extend an @internal abstract class must be flagged @internal or @final to not be captured by the BC checker.'];
        }

        return [];
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\in_array($namespace, self::TEST_CLASS_EXCEPTIONS, true)) {
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

    private function isInternal(string $doc): bool
    {
        return \str_contains($doc, '@internal') || \str_contains($doc, 'reason:becomes-internal');
    }

    private function isStorefrontController(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

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
        $class = $node->getClassReflection();

        foreach ($class->getInterfaces() as $interface) {
            if ($interface->getName() === EventSubscriberInterface::class) {
                return true;
            }
        }

        return false;
    }

    private function isInInternalNamespace(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

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

        return !empty($class->getAttributes(AsMessageHandler::class));
    }

    private function isFinal(ClassReflection $class, string $doc): bool
    {
        return str_contains($doc, '@final') || str_contains($doc, 'reason:becomes-final') || $class->isFinal();
    }

    private function isParentInternalAndAbstract(Scope $scope): bool
    {
        $parent = $scope->getClassReflection()->getParentClass();

        if ($parent === null) {
            return false;
        }

        if (!$parent->isAbstract()) {
            return false;
        }

        $native = $parent->getNativeReflection();

        $doc = $native->getDocComment() ?: '';

        return $this->isInternal($doc);
    }
}
