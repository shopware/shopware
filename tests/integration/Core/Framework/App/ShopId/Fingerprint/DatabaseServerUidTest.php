<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ShopId\Fingerprint;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint\DatabaseServerUid;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseServerUidTest extends TestCase
{
    use KernelTestBehaviour;

    private DatabaseServerUid $databaseServerUid;

    protected function setUp(): void
    {
        $this->databaseServerUid = static::getContainer()->get(DatabaseServerUid::class);
    }

    public function testGetIdentifier(): void
    {
        static::assertSame(DatabaseServerUid::IDENTIFIER, $this->databaseServerUid->getIdentifier());
    }

    public function testGetScore(): void
    {
        static::assertSame(100, $this->databaseServerUid->getScore());
    }

    public function testGetStamp(): void
    {
        $stamp = $this->databaseServerUid->getStamp();
        $dbUrlParts = parse_url($_SERVER['DATABASE_URL'] ?? '') ?: [];

        $databaseName = trim($dbUrlParts['path'] ?? '', '/');
        static::assertNotSame($databaseName, '');

        static::assertStringEndsWith($databaseName, $stamp);
        // expect server UID before table-space part
        static::assertNotSame($stamp, $databaseName);
    }
}
