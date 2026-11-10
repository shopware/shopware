<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A unit test runs the same on every machine and leaves nothing behind. Writing to the real filesystem, or
 * resolving a temp directory to write into, breaks both: the result depends on permissions, the working
 * directory and files left over by an earlier run, and a failing test leaks its files. This rule reports,
 * in the enabled unit namespaces ({@see Configuration}):
 *
 *  - the PHP functions that resolve a temp location or change the filesystem (`sys_get_temp_dir()`,
 *    `tempnam()`, `mkdir()`, `unlink()`, `file_put_contents()`, ...),
 *  - the writing methods of a Symfony `Filesystem` (`mkdir()`, `dumpFile()`, `remove()`, ...).
 *
 * Reading a checked-in fixture through `__DIR__` is fine, and so is a Flysystem with an in-memory adapter.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class NoRealFilesystemInUnitTestsRule implements Rule
{
    public const ERROR_FUNCTION = 'Unit tests must not call %s(): it touches the real filesystem, which differs between machines and keeps state between runs. Inject a stubbed Symfony Filesystem or a StaticFilesystem, read a checked-in fixture through __DIR__, or move the case to an integration test.';

    public const ERROR_METHOD = 'Unit tests must not call Filesystem::%s() on a real Symfony Filesystem: it touches the real filesystem, which differs between machines and keeps state between runs. Inject a stubbed Symfony Filesystem or a StaticFilesystem, read a checked-in fixture through __DIR__, or move the case to an integration test.';

    private const IDENTIFIER = 'shopware.realFilesystemInUnitTest';

    /**
     * PHP functions that resolve a location on the real filesystem or change it.
     */
    private const DENIED_FUNCTIONS = [
        'sys_get_temp_dir',
        'tempnam',
        'tmpfile',
        'getcwd',
        'chdir',
        'mkdir',
        'rmdir',
        'unlink',
        'rename',
        'copy',
        'touch',
        'symlink',
        'link',
        'chmod',
        'chown',
        'chgrp',
        'file_put_contents',
    ];

    /**
     * Methods of the Symfony Filesystem that change the real filesystem or resolve a temp file on it.
     */
    private const DENIED_FILESYSTEM_METHODS = [
        'appendtofile',
        'chgrp',
        'chmod',
        'chown',
        'copy',
        'dumpfile',
        'hardlink',
        'mirror',
        'mkdir',
        'remove',
        'rename',
        'symlink',
        'tempnam',
        'touch',
    ];

    /**
     * @var list<string>
     */
    private readonly array $enabledNamespaces;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        Configuration $configuration,
    ) {
        $this->enabledNamespaces = $configuration->getRealFilesystemInUnitTestsEnabledNamespaces();
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall && !$node instanceof MethodCall) {
            return [];
        }

        if (!$this->isEnabledNamespace($scope->getNamespace())) {
            return [];
        }

        if ($node instanceof FuncCall) {
            return $this->checkFunction($node, $scope);
        }

        return $this->checkMethod($node, $scope);
    }

    /**
     * @return list<RuleError>
     */
    private function checkFunction(FuncCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Name || !$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);
        if (!$function->isBuiltin() || !\in_array(strtolower($function->getName()), self::DENIED_FUNCTIONS, true)) {
            return [];
        }

        return [$this->error(\sprintf(self::ERROR_FUNCTION, $function->getName()))];
    }

    /**
     * @return list<RuleError>
     */
    private function checkMethod(MethodCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || !\in_array($node->name->toLowerString(), self::DENIED_FILESYSTEM_METHODS, true)) {
            return [];
        }

        if (!(new ObjectType(Filesystem::class))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [$this->error(\sprintf(self::ERROR_METHOD, $node->name->toString()))];
    }

    private function isEnabledNamespace(?string $namespace): bool
    {
        if ($namespace === null) {
            return false;
        }

        foreach ($this->enabledNamespaces as $enabledNamespace) {
            if (str_starts_with($namespace . '\\', $enabledNamespace)) {
                return true;
            }
        }

        return false;
    }

    private function error(string $message): RuleError
    {
        return RuleErrorBuilder::message($message)->identifier(self::IDENTIFIER)->build();
    }
}
