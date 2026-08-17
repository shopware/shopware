<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\EuVatIdPatternProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(EuVatIdPatternProvider::class)]
class EuVatIdPatternProviderTest extends TestCase
{
    public function testReturnsThePatternWithItsCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        $patterns = $provider->getPatterns();

        static::assertCount(2, $patterns);

        static::assertSame('BE', $patterns[0]->iso);
        static::assertSame('BE\d{10}', $patterns[0]->pattern);

        static::assertSame('NL', $patterns[1]->iso);
        static::assertSame('NL\d{9}B\d{2}', $patterns[1]->pattern);
    }

    public function testDropsPatternsThatDoNotCompile(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE[0-9'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        $patterns = $provider->getPatterns();

        static::assertCount(1, $patterns);
        static::assertSame('NL', $patterns[0]->iso);
    }

    public function testReadsThePatternsOnlyOncePerRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}']]);

        $provider = new EuVatIdPatternProvider($connection);

        static::assertEquals($provider->getPatterns(), $provider->getPatterns());
        static::assertNotNull($provider->matchVatId('NL123456789B01'));
    }

    public function testResetReloadsThePatterns(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}']]);

        $provider = new EuVatIdPatternProvider($connection);

        $provider->getPatterns();
        $provider->reset();
        $provider->getPatterns();
    }

    public function testMatchVatIdReturnsTheMemberStateItBelongsTo(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        $match = $provider->matchVatId('NL123456789B01');

        static::assertNotNull($match);
        static::assertSame('NL', $match->iso);
    }

    public function testMatchVatIdReturnsNullForANonEuVatId(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->matchVatId('CHE123456789'));
    }

    public function testMatchVatIdReturnsNullWhenNoCountryHasAPattern(): void
    {
        $provider = $this->createProvider([]);

        static::assertNull($provider->matchVatId('NL123456789B01'));
    }

    /**
     * @param list<array{iso: string, vat_id_pattern: string}> $rows
     */
    private function createProvider(array $rows): EuVatIdPatternProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new EuVatIdPatternProvider($connection);
    }
}
