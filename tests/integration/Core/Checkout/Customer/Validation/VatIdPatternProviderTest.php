<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class VatIdPatternProviderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @see Migration1718615305AddEuToCountryTable
     */
    private const EU_COUNTRY_ISO = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE',
        'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Patterns that do not compile are silently dropped while loading, so this asserts on the real
     * database that no shipped member state falls out of the list that way.
     */
    public function testEveryEuMemberStateShipsAPatternThatCompiles(): void
    {
        $provider = static::getContainer()->get(VatIdPatternProvider::class);

        static::assertSame(self::EU_COUNTRY_ISO, array_keys($provider->getEuPatterns()));
    }
}
