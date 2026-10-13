<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;

/**
 * A unit test never boots the kernel. This rule reports a unit test composing a kernel-booting behaviour,
 * following trait composition so a fixture trait cannot hide one. Direct `KernelLifecycleManager` calls are
 * the job of {@see NoKernelLifecycleManagerInUnitTestsRule}. Enforcement is limited to the unit namespaces
 * ({@see Configuration}); the migration suite runs against a database on purpose.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoKernelInUnitTestsRule implements Rule
{
    public const ERROR_TRAIT = 'Unit test composes %s, which boots the kernel and needs a database; it only passes because the unit CI job provides one. Move the test to tests/integration, or build the subject with its constructor and test doubles.';

    /**
     * Namespace prefixes whose traits reach for the kernel, the container or the database.
     */
    private const KERNEL_TRAIT_NAMESPACES = [
        'Shopware\Core\Framework\Test\TestCaseBase\\',
        'Shopware\Core\Framework\Test\DataAbstractionLayer\\',
    ];

    /**
     * Lifecycle behaviours that restore state after a test and never touch the kernel.
     */
    private const HARMLESS_TRAITS = [
        EnvTestBehaviour::class,
        EventDispatcherBehaviour::class,
    ];

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(Configuration $configuration)
    {
        $this->enabledNamespaces = $configuration->getKernelInUnitTestsEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (!TestRuleHelper::isTestClass($classReflection) || !$this->isEnabledNamespace($classReflection->getName())) {
            return [];
        }

        $errors = [];
        foreach ($this->composedTraits($classReflection) as $trait) {
            if (!$this->bootsKernel($trait)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_TRAIT, $trait))
                ->identifier('shopware.kernelInUnitTest')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Every trait the class composes, through its ancestors and through traits composing traits:
     * `getTraits(true)` only follows the class hierarchy, not the composition inside a trait.
     *
     * @return list<string>
     */
    private function composedTraits(ClassReflection $class): array
    {
        $traits = [];
        foreach ($class->getTraits(true) as $trait) {
            $traits[] = $trait->getName();
            $traits = [...$traits, ...$this->composedTraits($trait)];
        }

        return array_values(array_unique($traits));
    }

    private function bootsKernel(string $trait): bool
    {
        if (\in_array($trait, self::HARMLESS_TRAITS, true)) {
            return false;
        }

        foreach (self::KERNEL_TRAIT_NAMESPACES as $namespace) {
            if (\str_starts_with($trait, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function isEnabledNamespace(string $className): bool
    {
        foreach ($this->enabledNamespaces as $namespace) {
            if (\str_contains($className, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
