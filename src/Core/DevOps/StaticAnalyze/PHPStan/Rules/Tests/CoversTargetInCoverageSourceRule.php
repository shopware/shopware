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
 * PHPUnit 12 validates every CoversClass/CoversTrait target against the coverage
 * `<source>` of the running suite and reports targets outside of it as "not a valid
 * target for code coverage". PHPUnit 11 silently accepts them, so without this rule
 * such targets only surface once the PHPUnit 12 upgrade lands.
 *
 * The rule resolves each target to its source file and requires that file to be
 * inside the `<source><include>` list of phpunit.xml.dist and outside its
 * `<source><exclude>` list. Classes annotated with `@codeCoverageIgnore` stay valid
 * targets, so the annotation is deliberately not considered here.
 *
 * Enforced for the core unit suite only: the migration suite runs with its own
 * scoped coverage source, and downstream repositories load this rule set with their
 * own phpunit configurations.
 *
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class CoversTargetInCoverageSourceRule implements Rule
{
    private const UNIT_NAMESPACE = 'Shopware\Tests\Unit\\';

    /**
     * @var list<array{string, string}>|null resolved include entries as [directory, suffix]
     */
    private ?array $includes = null;

    /**
     * @var list<array{string, string}> resolved directory excludes as [directory, suffix]
     */
    private array $excludedDirectories = [];

    /**
     * @var list<string>
     */
    private array $excludedFiles = [];

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
        if (!\str_starts_with($classReflection->getName(), self::UNIT_NAMESPACE)) {
            return [];
        }

        if (!TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        if (!$this->loadCoverageSource()) {
            return [];
        }

        $errors = [];
        foreach ($this->getCoversTargets($node) as $target) {
            $file = $this->reflectionProvider->getClass($target)->getFileName();
            if ($file === null) {
                continue;
            }

            $relativePath = $this->relativePath($file);

            if ($relativePath === null || !$this->matchesAnyInclude($relativePath)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    '%s is not part of the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead.',
                    $target,
                ))->identifier('shopware.coversExcludedTarget')->build();

                continue;
            }

            if ($this->matchesAnyExclude($relativePath)) {
                $errors[] = RuleErrorBuilder::message(\sprintf(
                    '%s is excluded from the coverage source in phpunit.xml.dist, so it is not a valid coverage target under PHPUnit 12. Cover a class from the coverage source instead, or resolve the exclude.',
                    $target,
                ))->identifier('shopware.coversExcludedTarget')->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<string> resolved target class names
     */
    private function getCoversTargets(InClassNode $class): array
    {
        $targets = [];

        foreach ($class->getOriginalNode()->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (!\in_array($attribute->name->toString(), [CoversClass::class, CoversTrait::class], true)) {
                    continue;
                }

                $target = $this->resolveTargetName($attribute->args[0]->value ?? null);
                if ($target !== null && $this->reflectionProvider->hasClass($target)) {
                    $targets[] = $target;
                }
            }
        }

        return $targets;
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

    private function loadCoverageSource(): bool
    {
        if ($this->includes !== null) {
            return $this->includes !== [];
        }

        $this->includes = [];

        $configFile = $this->projectDir . '/phpunit.xml.dist';
        if (!\is_file($configFile)) {
            return false;
        }

        $config = simplexml_load_file($configFile);
        if ($config === false || !isset($config->source)) {
            return false;
        }

        foreach ($config->source->include->directory ?? [] as $directory) {
            $this->includes[] = [\trim((string) $directory, '/'), (string) ($directory['suffix'] ?? '.php')];
        }

        foreach ($config->source->exclude->directory ?? [] as $directory) {
            $this->excludedDirectories[] = [\trim((string) $directory, '/'), (string) ($directory['suffix'] ?? '.php')];
        }

        foreach ($config->source->exclude->file ?? [] as $file) {
            $this->excludedFiles[] = \trim((string) $file, '/');
        }

        return $this->includes !== [];
    }

    private function relativePath(string $file): ?string
    {
        $prefix = \rtrim($this->projectDir, '/') . '/';
        if (!\str_starts_with($file, $prefix)) {
            return null;
        }

        return \substr($file, \strlen($prefix));
    }

    private function matchesAnyInclude(string $relativePath): bool
    {
        foreach ($this->includes ?? [] as [$directory, $suffix]) {
            if (\str_starts_with($relativePath, $directory . '/') && \str_ends_with($relativePath, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function matchesAnyExclude(string $relativePath): bool
    {
        if (\in_array($relativePath, $this->excludedFiles, true)) {
            return true;
        }

        foreach ($this->excludedDirectories as [$directory, $suffix]) {
            if (\str_starts_with($relativePath, $directory . '/') && \str_ends_with($relativePath, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
