<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\UseMap;

/**
 * @internal
 */
#[CoversClass(UseMap::class)]
class UseMapTest extends TestCase
{
    #[TestDox('fromStmts returns alias to FQCN for regular use statements')]
    public function testRegularUses(): void
    {
        $map = UseMap::fromStmts($this->namespaceStmts(
            "use Doctrine\\DBAL\\Connection;\nuse Doctrine\\DBAL\\Types\\Types as DBALTypes;"
        ));

        static::assertSame('Doctrine\\DBAL\\Connection', $map['Connection'] ?? null);
        static::assertSame('Doctrine\\DBAL\\Types\\Types', $map['DBALTypes'] ?? null);
    }

    #[TestDox('fromStmts unfolds GroupUse declarations')]
    public function testGroupUse(): void
    {
        $map = UseMap::fromStmts($this->namespaceStmts(
            'use My\\Group\\{Inner1, Inner2 as Aliased};'
        ));

        static::assertSame('My\\Group\\Inner1', $map['Inner1'] ?? null);
        static::assertSame('My\\Group\\Inner2', $map['Aliased'] ?? null);
    }

    #[TestDox('fromStmts returns empty when there are no use statements')]
    public function testNoUses(): void
    {
        static::assertSame([], UseMap::fromStmts($this->namespaceStmts('class T {}')));
    }

    /**
     * @return array<Node>
     */
    private function namespaceStmts(string $body): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse("<?php\nnamespace T;\n" . $body . "\n");
        static::assertNotNull($stmts);

        $namespace = $stmts[0];
        static::assertInstanceOf(Namespace_::class, $namespace);

        return $namespace->stmts;
    }
}
