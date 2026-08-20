<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * A test's #[Package] value must match the #[Package] of at least one class or trait
 * named in its CoversClass/CoversTrait attributes, so the routing of a test cannot
 * drift from the ownership of the code it covers. `fundamentals@<area>` routes to the
 * <area> team and counts as equal to the plain <area> value in both directions.
 *
 * Enforced for the core unit suite only. The migration suite joins once it is
 * aligned: several migrations carry `framework` although they migrate feature-domain
 * tables, and there the source value is the one that has to change. Downstream
 * repositories load this rule set as well and carry their own package taxonomy,
 * so their test namespaces stay out.
 *
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class CoversPackageMatchRule implements Rule
{
    private const ENFORCED_NAMESPACE = 'Shopware\Tests\Unit\\';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
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
        $classReflection = $node->getClassReflection();

        if (!\str_starts_with($classReflection->getName(), self::ENFORCED_NAMESPACE)) {
            return [];
        }

        if (!TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        $testPackage = $this->getOwnPackage($node);
        if ($testPackage === null) {
            return [];
        }

        $coveredPackages = $this->getCoveredPackages($node);
        if ($coveredPackages === []) {
            return [];
        }

        $normalizedTestPackage = $this->normalize($testPackage);
        foreach ($coveredPackages as $coveredPackage) {
            if ($this->normalize($coveredPackage) === $normalizedTestPackage) {
                return [];
            }
        }

        $covered = [];
        foreach ($coveredPackages as $target => $coveredPackage) {
            $covered[] = \sprintf('%s (%s)', $target, $coveredPackage);
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'The #[Package(\'%s\')] attribute of this test does not match the covered %s',
                $testPackage,
                \implode(', ', $covered),
            ))
                ->identifier('shopware.coversPackageMismatch')
                ->build(),
        ];
    }

    private function getOwnPackage(InClassNode $class): ?string
    {
        foreach ($class->getOriginalNode()->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() !== Package::class) {
                    continue;
                }

                $argument = $attribute->args[0]->value ?? null;

                return $argument instanceof String_ ? $argument->value : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> covered class name => its #[Package] value; targets
     *                               without a resolvable package are left out, so a test
     *                               covering only unpackaged code is not checked
     */
    private function getCoveredPackages(InClassNode $class): array
    {
        $packages = [];

        foreach ($class->getOriginalNode()->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (!\in_array($attribute->name->toString(), [CoversClass::class, CoversTrait::class], true)) {
                    continue;
                }

                $target = $this->resolveTargetName($attribute->args[0]->value ?? null);
                if ($target === null || !$this->reflectionProvider->hasClass($target)) {
                    continue;
                }

                $package = $this->getReflectedPackage($target);
                if ($package !== null) {
                    $packages[$target] = $package;
                }
            }
        }

        return $packages;
    }

    private function resolveTargetName(?Node $argument): ?string
    {
        if ($argument instanceof ClassConstFetch && $argument->class instanceof Node\Name) {
            return $argument->class->toString();
        }

        if ($argument instanceof String_) {
            return $argument->value;
        }

        return null;
    }

    private function getReflectedPackage(string $className): ?string
    {
        $attributes = $this->reflectionProvider
            ->getClass($className)
            ->getNativeReflection()
            ->getAttributes(Package::class);

        if ($attributes === []) {
            return null;
        }

        $package = $attributes[0]->getArguments()[0] ?? null;

        return \is_string($package) ? $package : null;
    }

    private function normalize(string $package): string
    {
        return \str_starts_with($package, 'fundamentals@') ? \substr($package, \strlen('fundamentals@')) : $package;
    }
}
