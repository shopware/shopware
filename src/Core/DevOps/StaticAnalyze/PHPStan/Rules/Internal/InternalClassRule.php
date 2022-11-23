<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Api\ApiDefinition\ApiRoute\StoreApiTestOtherRoute;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @package core
 * @implements Rule<InClassNode>
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in 6.5.0
 */
class InternalClassRule implements Rule
{
    private const TEST_CLASS_EXCEPTIONS = [
        StoreApiTestOtherRoute::class, // The test route is used to test the OpenApiGenerator, that class would ignore internal classes
    ];

    private const INTERNAL_NAMESPACES = [
        '\\DevOps\\StaticAnalyze',
        '\\Framework\\Demodata',
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
        if ($this->isInternal($node)) {
            return [];
        }

        if ($this->isTestClass($node)) {
            return ['Test classes must be flagged @internal to not be captured by the BC checker'];
        }

        if ($this->isStorefrontController($node)) {
            return ['Storefront controllers must be flagged @internal to not be captured by the BC checker. The BC promise is checked over the route annotation.'];
        }

        if ($this->isBundle($node)) {
            return ['Bundles must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isEventSubscriber($node)) {
            $classDeprecation = $node->getClassReflection()->getDeprecatedDescription() ?? '';
            /**
             * @deprecated tag:v6.5.0 - remove deprecation check, as all listener should become internal in v6.5.0
             */
            if (\str_contains($classDeprecation, 'reason:becomes-internal') || \str_contains($classDeprecation, 'reason:remove-subscriber')) {
                return [];
            }

            return ['Event subscribers must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($namespace = $this->isInInternalNamespace($node)) {
            $classDeprecation = $node->getClassReflection()->getDeprecatedDescription() ?? '';
            /**
             * @deprecated tag:v6.5.0 - remove deprecation check, as all classes in internal namespaces should become internal in v6.5.0
             */
            if (\str_contains($classDeprecation, 'reason:becomes-internal')) {
                return [];
            }

            return ['Classes in `' . $namespace . '` namespace must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isMigrationStep($node)) {
            return ['Migrations must be flagged @internal to not be captured by the BC checker.'];
        }

        if ($this->isMessageHandler($node)) {
            $classDeprecation = $node->getClassReflection()->getDeprecatedDescription() ?? '';
            /**
             * @deprecated tag:v6.5.0 - remove deprecation check, as all migration steps become internal in v6.5.0
             */
            if (\str_contains($classDeprecation, 'tag:v6.5.0')) {
                return [];
            }

            return ['MessageHandlers must be flagged @internal to not be captured by the BC checker.'];
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

    private function isInternal(InClassNode $class): bool
    {
        $doc = $class->getDocComment();

        if ($doc === null) {
            return false;
        }

        return \str_contains($doc->getText(), '@internal') || \str_contains($doc->getText(), 'reason:becomes-internal');
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
        $class = $node->getClassReflection();

        if ($class->isAbstract()) {
            // abstract base classes should not be internal
            return false;
        }

        foreach ($class->getInterfaces() as $interface) {
            if ($interface->getName() === MessageHandlerInterface::class) {
                return true;
            }
        }

        return false;
    }
}
