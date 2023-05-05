<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
#[Package('core')]
class DeprecatedMethodsThrowDeprecationRule implements Rule
{
    /**
     * There are some exceptions to this rule, where deprecated methods should not throw a deprecation notice.
     * This is mainly the reason if the deprecated code is still called from inside the core due to BC reasons.
     */
    private const RULE_EXCEPTIONS = [
        // Subscribers still need to be called for BC reasons, therefore they do not trigger deprecations.
        'reason:remove-subscriber',
        // Decorators still need to be called for BC reasons, therefore they do not trigger deprecations.
        'reason:remove-decorator',
        // Command methods are still called from symfony, the execute method should throw a deprecation though.
        'reason:remove-command',
        // Entities still need to be present in the DI container, therefore they do not trigger deprecations.
        'reason:remove-entity',
        // Classes that will be internal are still called from inside the core, therefore they do not trigger deprecations.
        'reason:becomes-internal',
        // New function parameter will be added
        'reason:new-optional-parameter',
        // Classes that will be final, can only be changed with the next major
        'reason:becomes-final',
        // If the return type change, the functionality itself is not deprecated, therefore they do not trigger deprecations.
        'reason:return-type-change',
        // If there will be in the class hierarchy of a class we mark the whole class as deprecated, but the functionality itself is not deprecated, therefore they do not trigger deprecations.
        'reason:class-hierarchy-change',
        // If we change the visibility of a method we can't know from where it was called and whether the call will be valid in the future, therefore they do not trigger deprecations.
        'reason:visibility-change',
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->isInClass()) {
            // skip
            return [];
        }

        $class = $scope->getClassReflection();

        if ($class->isInterface() || $this->isTestClass($class)) {
            return [];
        }

        if (!($node->isPublic() || $node->isProtected()) || $node->isAbstract() || $node->isMagic()) {
            return [];
        }

        $methodContent = $this->getMethodContent($node, $scope, $class);
        $method = $class->getMethod($node->name->name, $scope);

        $classDeprecation = $class->getDeprecatedDescription();
        if ($classDeprecation && !$this->handlesDeprecationCorrectly($classDeprecation, $methodContent)) {
            return [
                \sprintf(
                    'Class "%s" is marked as deprecated, but method "%s" does not call "Feature::triggerDeprecationOrThrow". All public methods of deprecated classes need to trigger a deprecation warning.',
                    $class->getName(),
                    $method->getName()
                ),
            ];
        }

        $methodDeprecation = $method->getDeprecatedDescription() ?? '';

        // by default deprecations from parent methods are also available on all implementing methods
        // we will copy the deprecation to the implementing method, if they also have an affect there
        $deprecationOfParentMethod = !str_contains($method->getDocComment() ?? '', $methodDeprecation) && !str_contains($method->getDocComment() ?? '', 'inheritdoc');

        if (!$deprecationOfParentMethod && $methodDeprecation && !$this->handlesDeprecationCorrectly($methodDeprecation, $methodContent)) {
            return [
                \sprintf(
                    'Method "%s" of class "%s" is marked as deprecated, but does not call "Feature::triggerDeprecationOrThrow". All deprecated methods need to trigger a deprecation warning.',
                    $method->getName(),
                    $class->getName()
                ),
            ];
        }

        return [];
    }

    private function getMethodContent(Node $node, Scope $scope, ClassReflection $class): string
    {
        /** @var string $filename */
        $filename = $class->getFileName();

        $trait = $scope->getTraitReflection();

        if ($trait) {
            /** @var string $filename */
            $filename = $trait->getFileName();
        }

        $file = new \SplFileObject($filename);
        $file->seek($node->getStartLine() - 1);

        $content = '';
        for ($i = 0; $i <= ($node->getEndLine() - $node->getStartLine()); ++$i) {
            $content .= $file->current();
            $file->next();
        }

        return $content;
    }

    private function handlesDeprecationCorrectly(string $deprecation, string $method): bool
    {
        foreach (self::RULE_EXCEPTIONS as $exception) {
            if (\str_contains($deprecation, $exception)) {
                return true;
            }
        }

        return \str_contains($method, 'Feature::triggerDeprecationOrThrow(');
    }

    private function isTestClass(ClassReflection $class): bool
    {
        $namespace = $class->getName();

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        if ($class->getParentClass() === null) {
            return false;
        }

        return $class->getParentClass()->getName() === TestCase::class;
    }
}
