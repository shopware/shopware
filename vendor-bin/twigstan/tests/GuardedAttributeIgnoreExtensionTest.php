<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\Scope;
use PHPStan\TrinaryLogic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Twig\Extension\CoreExtension;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GuardedAttributeIgnoreExtension::class)]
class GuardedAttributeIgnoreExtensionTest extends TestCase
{
    #[DataProvider('guardCases')]
    public function testOnlyIgnoresGuardedMissingAttributes(string $identifier, bool $defined, bool $ignoreStrict, bool $narrowed, bool $expected): void
    {
        $arguments = array_fill(0, 8, new Arg(new Variable('value')));
        $arguments[6] = new Arg(new ConstFetch(new FullyQualified($defined ? 'true' : 'false')));
        $arguments[7] = new Arg(new ConstFetch(new FullyQualified($ignoreStrict ? 'true' : 'false')));
        $node = new StaticCall(new FullyQualified(CoreExtension::class), 'getAttribute', $arguments);
        $scope = static::createStub(Scope::class);
        $scope->method('hasExpressionType')->willReturn(TrinaryLogic::createFromBoolean($narrowed));
        // @phpstan-ignore phpstanApi.constructor (Test fixture for the pinned PHPStan version.)
        $error = new Error('Missing attribute.', 'template.php', identifier: $identifier);

        static::assertSame($expected, (new GuardedAttributeIgnoreExtension())->shouldIgnore($error, $node, $scope));
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool, bool}>
     */
    public static function guardCases(): iterable
    {
        yield 'existence check' => ['getAttribute.notFound', true, false, false, true];
        yield 'intermediate guarded lookup' => ['getAttribute.notFound', false, true, false, true];
        yield 'read narrowed by a guard' => ['getAttribute.notFound', false, false, true, true];
        yield 'unguarded read' => ['getAttribute.notFound', false, false, false, false];
        yield 'ordinary variable warning' => ['offsetAccess.notFound', true, true, true, false];
        yield 'unrelated method warning' => ['method.notFound', true, true, true, false];
        yield 'null receiver warning' => ['getAttribute.null', true, true, true, false];
    }

    public function testDoesNotIgnoreOtherNodesOrCalls(): void
    {
        // @phpstan-ignore phpstanApi.constructor (Test fixture for the pinned PHPStan version.)
        $error = new Error('Missing attribute.', 'template.php', identifier: 'getAttribute.notFound');
        $scope = static::createStub(Scope::class);
        foreach ([
            new Variable('value'),
            new StaticCall(new FullyQualified(\stdClass::class), 'getAttribute'),
            new StaticCall(new FullyQualified(CoreExtension::class), 'otherMethod'),
        ] as $node) {
            static::assertFalse((new GuardedAttributeIgnoreExtension())->shouldIgnore($error, $node, $scope));
        }
    }
}
