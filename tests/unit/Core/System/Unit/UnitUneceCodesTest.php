<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Unit\UnitUneceCodes;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(UnitUneceCodes::class)]
class UnitUneceCodesTest extends TestCase
{
    private UnitUneceCodes $uneceCodes;

    protected function setUp(): void
    {
        $this->uneceCodes = new UnitUneceCodes();
    }

    public function testGetCodesListsRecommendation20And21CodesWithName(): void
    {
        $codes = $this->uneceCodes->getCodes();

        static::assertContains(['code' => 'H87', 'name' => 'piece'], $codes);
        static::assertContains(['code' => 'KGM', 'name' => 'kilogram'], $codes);
        static::assertContains(['code' => 'XBX', 'name' => 'box'], $codes);
    }

    public function testGetCodesKeepsNumericCodesAsStrings(): void
    {
        static::assertContains(['code' => '10', 'name' => 'group'], $this->uneceCodes->getCodes());
    }

    public function testGetCodesHasEachCodeOnce(): void
    {
        $codes = array_column($this->uneceCodes->getCodes(), 'code');

        static::assertCount(\count($codes), array_unique($codes));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodeProvider(): iterable
    {
        yield 'recommendation 20 unit of measure' => ['KGM'];
        yield 'recommendation 20 code with a digit' => ['C62'];
        yield 'recommendation 21 package type' => ['XPX'];
    }

    #[DataProvider('validCodeProvider')]
    public function testIsValidAcceptsListedCodes(string $code): void
    {
        static::assertTrue($this->uneceCodes->isValid($code));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodeProvider(): iterable
    {
        yield 'lowercase version of a listed code' => ['kgm'];
        yield 'translated short code instead of the standard code' => ['Stk'];
        yield 'unknown code' => ['ZZZ'];
        yield 'empty string' => [''];
    }

    #[DataProvider('invalidCodeProvider')]
    public function testIsValidRejectsUnlistedCodes(string $code): void
    {
        static::assertFalse($this->uneceCodes->isValid($code));
    }
}
