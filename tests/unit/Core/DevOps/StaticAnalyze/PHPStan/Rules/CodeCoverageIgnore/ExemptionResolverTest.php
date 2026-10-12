<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\ExemptionResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class ExemptionResolverTest extends TestCase
{
    private const EXISTING_TEST = 'Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookHealthServiceTest';

    private const EXISTING_DEVOPS_TEST = 'Shopware\Tests\DevOps\Core\Installer\InstallerKernelTest';

    /**
     * @param array<string, string> $useMap
     */
    #[TestDox('isExempted: $_dataName')]
    #[DataProvider('caseProvider')]
    public function testIsExempted(string $docComment, array $useMap, bool $expected): void
    {
        // a real ReflectionProvider would boot the whole PHPStan container; the resolver
        // only asks hasClass(), and the end-to-end path runs in the devops rule test
        $reflectionProvider = static::createStub(ReflectionProvider::class);
        $reflectionProvider->method('hasClass')
            ->willReturnCallback(static fn (string $class): bool => \in_array($class, [self::EXISTING_TEST, self::EXISTING_DEVOPS_TEST], true));

        $resolver = new ExemptionResolver($reflectionProvider);

        $node = $this->makeClassWithDoc($docComment);

        static::assertSame($expected, $resolver->isExempted($node, $useMap));
    }

    /**
     * @return \Generator<string, array{0: string, 1: array<string, string>, 2: bool}>
     */
    public static function caseProvider(): \Generator
    {
        yield 'no docblock' => ['', [], false];

        yield 'docblock without @see' => ['/** @internal */', [], false];

        yield 'FQCN to existing integration test exempts' => [
            '/** @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\WebhookHealthServiceTest */',
            [],
            true,
        ];

        yield 'FQCN to existing devops test exempts' => [
            '/** @see \\Shopware\\Tests\\DevOps\\Core\\Installer\\InstallerKernelTest */',
            [],
            true,
        ];

        yield 'FQCN to non-existent devops test does not exempt' => [
            '/** @see \\Shopware\\Tests\\DevOps\\Definitely\\Not\\A\\RealTest */',
            [],
            false,
        ];

        yield 'FQCN to non-existent integration test does not exempt' => [
            '/** @see \\Shopware\\Tests\\Integration\\Definitely\\Not\\A\\RealTest */',
            [],
            false,
        ];

        yield 'FQCN to unit test (not integration) does not exempt' => [
            '/** @see \\Shopware\\Tests\\Unit\\Core\\Framework\\SomeUnitTest */',
            [],
            false,
        ];

        yield '::method suffix on the reference is stripped' => [
            '/** @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\WebhookHealthServiceTest::testFoo */',
            [],
            true,
        ];

        yield 'short-form @see resolved through the use map exempts' => [
            '/** @see WebhookHealthServiceTest */',
            ['WebhookHealthServiceTest' => 'Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\WebhookHealthServiceTest'],
            true,
        ];

        yield 'short-form @see not in the use map does not exempt' => [
            '/** @see WebhookHealthServiceTest */',
            [],
            false,
        ];

        yield 'multiple @see; one valid is enough' => [
            "/**\n * @see SomeBogus\n * @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\WebhookHealthServiceTest\n */",
            [],
            true,
        ];
    }

    private function makeClassWithDoc(string $docComment): Node
    {
        $source = '<?php' . "\n";
        if ($docComment !== '') {
            $source .= $docComment . "\n";
        }
        $source .= 'class T {}';

        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse($source);
        static::assertNotNull($stmts);

        $node = (new NodeFinder())->findFirstInstanceOf($stmts, Class_::class);
        static::assertInstanceOf(Class_::class, $node);

        return $node;
    }
}
