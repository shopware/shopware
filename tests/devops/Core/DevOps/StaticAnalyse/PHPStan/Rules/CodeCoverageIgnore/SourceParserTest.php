<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\CodeCoverageIgnore;

use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\SourceParser;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation\LogicTrait;

/**
 * @internal
 */
class SourceParserTest extends PHPStanTestCase
{
    #[TestDox('traitMethods returns the trait methods')]
    public function testTraitMethodsReturnsTraitMethods(): void
    {
        $parser = $this->parser();

        $methods = $parser->traitMethods(LogicTrait::class);

        static::assertCount(1, $methods);
        static::assertNotNull($methods[0]->name);
        static::assertSame('doSomething', $methods[0]->name->name);
    }

    #[TestDox('traitMethods returns empty for a class the reflection provider does not know')]
    public function testTraitMethodsReturnsEmptyForUnknownClass(): void
    {
        static::assertSame([], $this->parser()->traitMethods('Shopware\\Definitely\\Not\\A\\Real\\TraitClass'));
    }

    #[TestDox('traitMethods returns empty for a non-trait class')]
    public function testTraitMethodsReturnsEmptyForNonTrait(): void
    {
        static::assertSame([], $this->parser()->traitMethods(\stdClass::class));
    }

    #[TestDox('traitMethods caches per-trait results')]
    public function testTraitMethodsCachesResults(): void
    {
        $parser = $this->parser();

        static::assertSame($parser->traitMethods(LogicTrait::class), $parser->traitMethods(LogicTrait::class));
    }

    #[TestDox('useMap returns alias to FQCN for regular use statements')]
    public function testUseMapExtractsImports(): void
    {
        $map = $this->parser()->useMap(__DIR__ . '/_fixtures/UseMapFixture.php');

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
            $map = $this->parser()->useMap($tmp);
            static::assertSame('My\\Group\\Inner1', $map['Inner1'] ?? null);
            static::assertSame('My\\Group\\Inner2', $map['Aliased'] ?? null);
        } finally {
            @unlink($tmp);
        }
    }

    #[TestDox('useMap returns empty for a missing file')]
    public function testUseMapReturnsEmptyForMissingFile(): void
    {
        static::assertSame([], $this->parser()->useMap('/path/that/does/not/exist.php'));
    }

    private function parser(): SourceParser
    {
        return new SourceParser(self::createReflectionProvider());
    }
}
