<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1674704527UpdateVATPatternForCyprusCountry
 */
#[Package('customer-order')]
class Migration1674704527UpdateVATPatternForCyprusCountryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetPatternForCyprusCountry(): void
    {
        $vatPattern = (string) $this->connection->fetchOne(
            'SELECT vat_id_pattern FROM country WHERE iso = :iso;',
            ['iso' => 'CY']
        );

        static::assertSame($vatPattern, '(CY)?[0-9]{8}[A-Z]{1}');
    }
}
