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
use PHPStan\Type\NeverType;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A unit test runs the same on every machine and leaves nothing behind, so it never touches the real filesystem.
 * In the enabled unit namespaces ({@see Configuration}) this rule reports the PHP functions that resolve a temp
 * location or the working directory or change the filesystem, and the writing methods called on a real Symfony
 * Filesystem. Doubles and subclasses such as InMemoryUtilFilesystem pass, and so do string callables and stream
 * handles: the rule is a deny list, not a sandbox.
 *
 * @implements Rule<CallLike>
 *
 * @internal
 */
#[Package('framework')]
class NoRealFilesystemInUnitTestsRule implements Rule
{
    public const ERROR_FUNCTION = 'Unit tests must not call %s(): it touches the real filesystem, which differs between machines and keeps state between runs. Drive the subject with an InMemoryUtilFilesystem or a stubbed Symfony Filesystem, read a checked-in fixture through __DIR__, or move the case to an integration test.';

    public const ERROR_METHOD = 'Unit tests must not call Filesystem::%s() on a real Symfony Filesystem: it touches the real filesystem, which differs between machines and keeps state between runs. Use an InMemoryUtilFilesystem or a stubbed Symfony Filesystem instead, or move the case to an integration test.';

    private const IDENTIFIER = 'shopware.realFilesystemInUnitTest';

    /**
     * Resolve a temp location or the working directory, or change the filesystem.
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
     * The writing methods of the Symfony Filesystem.
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

        $receiverType = $scope->getType($node->var);
        if ($receiverType instanceof NeverType || $receiverType->getObjectClassNames() !== [Filesystem::class]) {
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
