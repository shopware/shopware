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
use Symfony\Component\Finder\Finder;

/**
 * A test's #[Package] value must match the ownership of the code it exercises, so the
 * routing of a test cannot drift from the ownership of that code. `fundamentals@<area>`
 * routes to the <area> team and counts as equal to the plain <area> value in both
 * directions.
 *
 * Unit and migration tests are compared against the #[Package] of the classes and
 * traits named in their CoversClass/CoversTrait attributes. Integration tests carry
 * no covers attributes; they are compared against the #[Package] values found in the
 * nearest existing `src/` directory their namespace mirrors, and pass when they match
 * at least one of them.
 *
 * Enforced for the core test suites only. Downstream repositories load this rule set
 * as well and carry their own package taxonomy, so their test namespaces stay out.
 *
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class TestPackageMatchRule implements Rule
{
    private const UNIT_NAMESPACE = 'Shopware\Tests\Unit\\';

    private const MIGRATION_NAMESPACE = 'Shopware\Tests\Migration\\';

    private const INTEGRATION_NAMESPACE = 'Shopware\Tests\Integration\\';

    /**
     * @var array<string, list<string>> resolved src directory => distinct #[Package] values found in it
     */
    private array $mirroredPackages = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly string $projectDir,
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
        $className = $classReflection->getName();

        $covers = \str_starts_with($className, self::UNIT_NAMESPACE)
            || \str_starts_with($className, self::MIGRATION_NAMESPACE);
        if (!$covers && !\str_starts_with($className, self::INTEGRATION_NAMESPACE)) {
            return [];
        }

        if (!TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        $testPackage = $this->getOwnPackage($node);
        if ($testPackage === null) {
            return [];
        }

        return $covers
            ? $this->matchCoveredClasses($node, $testPackage)
            : $this->matchMirroredDirectory($className, $testPackage);
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    private function matchCoveredClasses(InClassNode $node, string $testPackage): array
    {
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

    /**
     * @return array<array-key, RuleError|string>
     */
    private function matchMirroredDirectory(string $className, string $testPackage): array
    {
        $directory = $this->resolveMirroredDirectory($className);
        if ($directory === null) {
            return [];
        }

        $packages = $this->getMirroredPackages($directory);
        if ($packages === []) {
            return [];
        }

        $normalizedTestPackage = $this->normalize($testPackage);
        foreach ($packages as $package) {
            if ($this->normalize($package) === $normalizedTestPackage) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'The #[Package(\'%s\')] attribute of this test does not match the mirrored src/%s (%s)',
                $testPackage,
                \substr($directory, \strlen($this->projectDir . '/src/')),
                \implode(', ', $packages),
            ))
                ->identifier('shopware.mirroredPackageMismatch')
                ->build(),
        ];
    }

    /**
     * Maps the test namespace onto the `src/` tree and walks up until an existing
     * directory is found, so tests in deeper scenario folders still resolve to the
     * code they exercise. Returns null when not even the first segment exists.
     */
    private function resolveMirroredDirectory(string $className): ?string
    {
        $relative = \substr($className, \strlen(self::INTEGRATION_NAMESPACE));
        $segments = \explode('\\', $relative);
        \array_pop($segments);

        while ($segments !== []) {
            $directory = $this->projectDir . '/src/' . \implode('/', $segments);
            if (\is_dir($directory)) {
                return $directory;
            }

            \array_pop($segments);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getMirroredPackages(string $directory): array
    {
        if (isset($this->mirroredPackages[$directory])) {
            return $this->mirroredPackages[$directory];
        }

        $packages = [];
        // the frontend roots are large and carry no #[Package] classes; excluding them prunes the traversal
        $files = (new Finder())->files()->in($directory)->exclude(['node_modules', 'Resources/app'])->name('*.php');
        foreach ($files as $file) {
            if (\preg_match_all('/^#\[Package\(\'([^\']+)\'/m', $file->getContents(), $matches) > 0) {
                foreach ($matches[1] as $package) {
                    $packages[$package] = true;
                }
            }
        }

        return $this->mirroredPackages[$directory] = \array_keys($packages);
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
