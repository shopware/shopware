<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionMethod as BetterReflectionMethod;
use PHPStan\BetterReflection\Reflection\ReflectionMethod;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use Shopware\Core\Framework\App\AppUrlChangeResolver\AbstractAppUrlChangeStrategy;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('core')]
class DecorationPatternRule implements Rule
{
    use InTestClassTrait;

    private const SKIP = [
        AbstractExtensionStoreLicensesService::class,
        AbstractStoreAppLifecycleService::class,
        AbstractExtensionLifecycle::class,
        AbstractExtensionDataProvider::class,
        AbstractAppUpdater::class,
        RefreshableAppDryRun::class,
        RefreshableAppDryRun::class,
        AbstractAppLoader::class,
        AbstractAppLifecycle::class,
        AbstractAppUrlChangeStrategy::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        // any anonymous class can be skipped
        if (\str_starts_with($scope->getClassReflection()->getName(), 'AnonymousClass')) {
            return [];
        }

        $class = $scope->getClassReflection();

        // some classes can be skipped because they are only for internal decorations (e.g. App stuff)
        if (\in_array($class->getName(), self::SKIP, true)) {
            return [];
        }

        // only validate classes that has a getDecorated method
        if (!$this->hasDecorationPattern($class, $scope)) {
            return [];
        }

        // validate the abstract definition
        if ($class->isAbstract()) {
            return $this->validateAbstractClass($node, $class);
        }

        if (!$this->isBaseImplementation($node)) {
            return [];
        }

        $parent = $class->getParentClass();

        // only validate classes that extend from abstract Shopware classes
        if (!$parent || !\str_starts_with($parent->getName(), 'Shopware\\') || !$parent->isAbstract()) {
            return [];
        }

        // validate if the parent class has a decorator pattern or if it is deprecated
        if (!$this->hasDecorationPattern($parent, $scope)) {
            return [];
        }

        // some classes can be skipped because they are only for internal decorations (e.g. App stuff)
        if (\in_array($parent->getName(), self::SKIP, true)) {
            return [];
        }

        $errors = [];

        $doc = $node->getDocComment()?->getText() ?? '';
        if ($this->isInternal($doc)) {
            $errors[] = 'Decoration error: Concrete class is marked as @internal. Remove `getDecorated` (if not intended that these classes can be decorated) or remove @internal annotation';
        }

        if ($class->implementsInterface(EventSubscriberInterface::class)) {
            $errors[] = 'Decoration error: Decoration pattern is not compatible with event subscribers. Remove `getDecorated` (if not intended that these classes can be decorated) or extract EventSubscriberInterface into own class';
        }

        // loop all methods and ensure that all public function also inside the parent class
        /** @var ReflectionMethod $method */
        foreach ($class->getNativeReflection()->getMethods() as $method) {
            if (!$this->isPublic($method)) {
                continue;
            }

            if ($method->getName() === '__construct') {
                continue;
            }

            if ($parent->hasMethod($method->getName())) {
                continue;
            }

            $errors[] = \sprintf('Decoration error: Concrete class has a public method %s which is not defined in the parent class %s', $method->getName(), $parent->getName());
        }

        return $errors;
    }

    private function isInternal(string $doc): bool
    {
        return str_contains($doc, '@internal') || str_contains($doc, 'reason:becomes-internal');
    }

    private function isFinal(ClassReflection $class, string $doc): bool
    {
        return str_contains($doc, '@final') || str_contains($doc, 'reason:becomes-final') || $class->isFinal();
    }

    private function hasDecorationPattern(ClassReflection $class, Scope $scope): bool
    {
        if (!$class->hasMethod('getDecorated')) {
            return false;
        }

        $method = $class->getMethod('getDecorated', $scope);

        $doc = $method->getDocComment() ?? '';

        return !\str_contains($doc, '@deprecated');
    }

    /**
     * @return array<string>
     */
    private function validateAbstractClass(InClassNode $node, ClassReflection $class): array
    {
        $doc = $node->getDocComment()?->getText() ?? '';

        if ($this->isInternal($doc)) {
            return ['Decoration error: Abstract class is marked as @internal, but has a decoration pattern. Remove `getDecorated` (if not intended that these classes can be decorated) or remove @internal annotation'];
        }

        if ($this->isFinal($class, $doc)) {
            return ['Decoration error: Abstract class is marked as @final which makes no sense. Please remove @final state'];
        }

        return [];
    }

    private function isPublic(BetterReflectionMethod|ReflectionMethod $method): bool
    {
        if ($method->isStatic() || !$method->isPublic()) {
            return false;
        }

        $doc = $method->getDocComment() ?? '';

        return !\str_contains((string) $doc, 'reason:visibility-change');
    }

    private function isBaseImplementation(InClassNode $node): bool
    {
        $method = $node->getOriginalNode()->getMethod('getDecorated');

        if (!$method) {
            return false;
        }

        $firstStatement = ($method->getStmts() ?? [])[0];

        if ($firstStatement instanceof Node\Stmt\Throw_) {
            return true;
        }

        return false;
    }
}
