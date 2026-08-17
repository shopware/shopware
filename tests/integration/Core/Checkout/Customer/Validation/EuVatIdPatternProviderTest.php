<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\EuVatIdPatternProvider;
use Shopware\Core\Checkout\Customer\Validation\VatIdPattern;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class EuVatIdPatternProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @see Migration1718615305AddEuToCountryTable
     */
    private const EU_COUNTRY_ISO = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE',
        'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    private EuVatIdPatternProvider $provider;

    protected function setUp(): void
    {
        $this->provider = static::getContainer()->get(EuVatIdPatternProvider::class);
        $this->provider->reset();
    }

    /**
     * Patterns that do not compile are dropped while loading, so a missing ISO code
     * also means the pattern of that is broken.
     */
    public function testProvidesACompilingPatternForEveryEuMemberState(): void
    {
        $isoCodes = array_map(
            static fn (VatIdPattern $pattern): string => $pattern->iso,
            $this->provider->getPatterns(),
        );

        static::assertSame(self::EU_COUNTRY_ISO, $isoCodes);
    }

    public function testResolvesAVatIdToItsMemberState(): void
    {
        $match = $this->provider->matchVatId('NL123456789B01');

        static::assertNotNull($match);
        static::assertSame('NL', $match->iso);
    }

    public function testResolvesNoMemberStateForANonEuVatId(): void
    {
        static::assertNull($this->provider->matchVatId('CHE123456789'));
        static::assertNull($this->provider->matchVatId('INVALID'));
    }
}
