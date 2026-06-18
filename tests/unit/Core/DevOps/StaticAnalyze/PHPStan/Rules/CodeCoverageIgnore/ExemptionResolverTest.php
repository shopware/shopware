<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\ExemptionResolver;

/**
 * @internal
 */
#[CoversClass(ExemptionResolver::class)]
class ExemptionResolverTest extends PHPStanTestCase
{
    /**
     * @param array<string, string> $useMap
     */
    #[TestDox('isExempted: $_dataName')]
    #[DataProvider('caseProvider')]
    public function testIsExempted(string $docComment, array $useMap, bool $expected): void
    {
        $resolver = new ExemptionResolver(self::createReflectionProvider());

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
            '/** @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\RelatedWebhooksTest */',
            [],
            true,
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
            '/** @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\RelatedWebhooksTest::testFoo */',
            [],
            true,
        ];

        yield 'short-form @see resolved through the use map exempts' => [
            '/** @see RelatedWebhooksTest */',
            ['RelatedWebhooksTest' => 'Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\RelatedWebhooksTest'],
            true,
        ];

        yield 'short-form @see not in the use map does not exempt' => [
            '/** @see RelatedWebhooksTest */',
            [],
            false,
        ];

        yield 'multiple @see; one valid is enough' => [
            "/**\n * @see SomeBogus\n * @see \\Shopware\\Tests\\Integration\\Core\\Framework\\Webhook\\Service\\RelatedWebhooksTest\n */",
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
