<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Unit\UnitIsoCodes;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(UnitIsoCodes::class)]
class UnitIsoCodesTest extends TestCase
{
    private UnitIsoCodes $isoCodes;

    protected function setUp(): void
    {
        $this->isoCodes = new UnitIsoCodes();
    }

    public function testGetCodesNamesRecommendation20And21Codes(): void
    {
        $codes = $this->isoCodes->getCodes();

        static::assertSame('piece', $codes['H87']);
        static::assertSame('kilogram', $codes['KGM']);
        static::assertSame('box', $codes['XBX']);
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
        static::assertTrue($this->isoCodes->isValid($code));
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
        static::assertFalse($this->isoCodes->isValid($code));
    }
}
