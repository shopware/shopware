<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\SourceParser;

/**
 * @internal
 */
#[CoversClass(SourceParser::class)]
class SourceParserTest extends TestCase
{
    #[TestDox('useMap returns alias to FQCN for regular use statements')]
    public function testUseMapExtractsImports(): void
    {
        $map = (new SourceParser())->useMap(__DIR__ . '/_fixtures/UseMapFixture.php');

        static::assertSame('Doctrine\\DBAL\\Connection', $map['Connection'] ?? null);
        static::assertSame('Doctrine\\DBAL\\Types\\Types', $map['DBALTypes'] ?? null);
    }

    #[TestDox('useMap unfolds GroupUse declarations')]
    public function testUseMapHandlesGroupUse(): void
    {
        // GroupUse must live in a runtime-generated file: cs-fixer flattens it
        // to single-import statements in any committed fixture.
        $tmp = tempnam(sys_get_temp_dir(), 'usemap_groupuse_') . '.php';
        file_put_contents(
            $tmp,
            "<?php\nnamespace T;\nuse My\\Group\\{Inner1, Inner2 as Aliased};\n",
        );

        try {
            $map = (new SourceParser())->useMap($tmp);
            static::assertSame('My\\Group\\Inner1', $map['Inner1'] ?? null);
            static::assertSame('My\\Group\\Inner2', $map['Aliased'] ?? null);
        } finally {
            @unlink($tmp);
        }
    }

    #[TestDox('useMap returns empty for a missing file')]
    public function testUseMapReturnsEmptyForMissingFile(): void
    {
        static::assertSame([], (new SourceParser())->useMap('/path/that/does/not/exist.php'));
    }
}
