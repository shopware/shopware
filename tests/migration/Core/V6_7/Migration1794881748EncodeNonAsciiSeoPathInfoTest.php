<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794881748EncodeNonAsciiSeoPathInfo;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1794881748EncodeNonAsciiSeoPathInfo::class)]
class Migration1794881748EncodeNonAsciiSeoPathInfoTest extends TestCase
{
    private const ROUTE_NAME = 'migration.test.encode-non-ascii';

    private Connection $connection;

    private string $languageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();

        $languageId = $this->connection->fetchOne('SELECT `id` FROM `language` LIMIT 1');
        static::assertIsString($languageId);
        $this->languageId = $languageId;
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794881748, (new Migration1794881748EncodeNonAsciiSeoPathInfo())->getCreationTimestamp());
    }

    public function testUpdateEncodesNonAsciiCharacters(): void
    {
        $raw = $this->insertSeoUrl('Babyöl/grün?farbe=weiß');
        $ascii = $this->insertSeoUrl('Computers/Laptops');
        $encoded = $this->insertSeoUrl('caf%C3%A9');

        $migration = new Migration1794881748EncodeNonAsciiSeoPathInfo();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('Baby%C3%B6l/gr%C3%BCn?farbe=wei%C3%9F', $this->fetchSeoPathInfo($raw));
        static::assertSame('Computers/Laptops', $this->fetchSeoPathInfo($ascii));
        static::assertSame('caf%C3%A9', $this->fetchSeoPathInfo($encoded));
    }

    public function testUpdateKeepsARawPathWhoseEncodedFormIsAlreadyStored(): void
    {
        // the unique index only applies to rows with a sales channel, as NULL never collides
        $salesChannelId = $this->connection->fetchOne('SELECT `id` FROM `sales_channel` LIMIT 1');
        static::assertIsString($salesChannelId);

        $raw = $this->insertSeoUrl('Babyöl', $salesChannelId);
        $encoded = $this->insertSeoUrl('Baby%C3%B6l', $salesChannelId);

        (new Migration1794881748EncodeNonAsciiSeoPathInfo())->update($this->connection);

        static::assertSame('Babyöl', $this->fetchSeoPathInfo($raw));
        static::assertSame('Baby%C3%B6l', $this->fetchSeoPathInfo($encoded));
    }

    public function testUpdateKeepsARawPathWhoseEncodedFormDoesNotFitTheColumn(): void
    {
        $path = str_repeat('ö', 200);
        $raw = $this->insertSeoUrl($path);

        (new Migration1794881748EncodeNonAsciiSeoPathInfo())->update($this->connection);

        static::assertSame($path, $this->fetchSeoPathInfo($raw));
    }

    private function insertSeoUrl(string $seoPathInfo, ?string $salesChannelId = null): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('seo_url', [
            'id' => $id,
            'sales_channel_id' => $salesChannelId,
            'language_id' => $this->languageId,
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => self::ROUTE_NAME,
            'path_info' => '/detail/' . Uuid::randomHex(),
            'seo_path_info' => $seoPathInfo,
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        return $id;
    }

    private function fetchSeoPathInfo(string $id): string
    {
        $seoPathInfo = $this->connection->fetchOne('SELECT `seo_path_info` FROM `seo_url` WHERE `id` = :id', ['id' => $id]);
        static::assertIsString($seoPathInfo);

        return $seoPathInfo;
    }
}
