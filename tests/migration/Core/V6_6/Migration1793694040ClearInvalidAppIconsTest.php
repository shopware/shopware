<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1793694040ClearInvalidAppIcons;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793694040ClearInvalidAppIcons::class)]
class Migration1793694040ClearInvalidAppIconsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->connection->rollBack();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793694040, (new Migration1793694040ClearInvalidAppIcons())->getCreationTimestamp());
    }

    public function testInvalidAppAndFlowActionIconsAreCleared(): void
    {
        $validAppId = $this->createApp(self::png());
        $leakedAppId = $this->createApp("APP_SECRET=secret\nDATABASE_URL=mysql://root:root@localhost/shopware");
        $oversizedAppId = $this->createApp(self::png() . str_repeat("\0", Filesystem::MAX_IMAGE_SIZE));
        $withoutIconAppId = $this->createApp(null);

        $validFlowActionId = $this->createFlowAction($validAppId, self::png());
        $leakedFlowActionId = $this->createFlowAction($validAppId, 'secret');

        $migration = new Migration1793694040ClearInvalidAppIcons();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(self::png(), $this->fetchIcon('app', $validAppId));
        static::assertNull($this->fetchIcon('app', $leakedAppId));
        static::assertNull($this->fetchIcon('app', $oversizedAppId));
        static::assertNull($this->fetchIcon('app', $withoutIconAppId));

        static::assertSame(self::png(), $this->fetchIcon('app_flow_action', $validFlowActionId));
        static::assertNull($this->fetchIcon('app_flow_action', $leakedFlowActionId));
    }

    public function testInvalidPaymentAndShippingMethodIconMediaIsUnlinked(): void
    {
        $imageMediaId = $this->createMedia('image/png');
        $svgMediaId = $this->createMedia('image/svg+xml');
        $leakedPaymentMediaId = $this->createMedia('text/plain');
        $leakedShippingMediaId = $this->createMedia('text/csv');

        $validPayment = $this->createPaymentMethod($imageMediaId);
        $svgPayment = $this->createPaymentMethod($svgMediaId);
        $leakedPayment = $this->createPaymentMethod($leakedPaymentMediaId);
        $validShipping = $this->createShippingMethod($imageMediaId);
        $leakedShipping = $this->createShippingMethod($leakedShippingMediaId);

        $migration = new Migration1793694040ClearInvalidAppIcons();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([$imageMediaId, $imageMediaId], $this->fetchMediaIds('payment_method', $validPayment));
        static::assertSame([$svgMediaId, $svgMediaId], $this->fetchMediaIds('payment_method', $svgPayment));
        static::assertSame([null, null], $this->fetchMediaIds('payment_method', $leakedPayment));
        static::assertSame([$imageMediaId, $imageMediaId], $this->fetchMediaIds('shipping_method', $validShipping));
        static::assertSame([null, null], $this->fetchMediaIds('shipping_method', $leakedShipping));
    }

    public function testMerchantMediaOnTheMethodIsKept(): void
    {
        $leakedMediaId = $this->createMedia('text/plain');
        $merchantMediaId = $this->createMedia('image/png');

        $paymentMethodId = $this->createPaymentMethod($leakedMediaId, $merchantMediaId);

        (new Migration1793694040ClearInvalidAppIcons())->update($this->connection);

        static::assertSame([$merchantMediaId, null], $this->fetchMediaIds('payment_method', $paymentMethodId));
    }

    private function createApp(?string $icon): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('app', [
            'id' => $id,
            'name' => 'TestApp' . Uuid::fromBytesToHex($id),
            'version' => '1.0.0',
            'icon' => $icon,
            'integration_id' => Uuid::randomBytes(),
            'acl_role_id' => Uuid::randomBytes(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function createFlowAction(string $appId, string $icon): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('app_flow_action', [
            'id' => $id,
            'app_id' => $appId,
            'name' => 'action' . Uuid::fromBytesToHex($id),
            'url' => 'https://example.com/flow',
            'icon' => $icon,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function createMedia(string $mimeType): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('media', [
            'id' => $id,
            'mime_type' => $mimeType,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function createPaymentMethod(string $originalMediaId, ?string $mediaId = null): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('payment_method', [
            'id' => $id,
            'handler_identifier' => 'app\TestApp_' . Uuid::fromBytesToHex($id),
            'technical_name' => 'payment_test_' . Uuid::fromBytesToHex($id),
            'media_id' => $mediaId ?? $originalMediaId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('app_payment_method', [
            'id' => Uuid::randomBytes(),
            'app_name' => 'TestApp',
            'identifier' => Uuid::fromBytesToHex($id),
            'payment_method_id' => $id,
            'original_media_id' => $originalMediaId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function createShippingMethod(string $originalMediaId): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('shipping_method', [
            'id' => $id,
            'technical_name' => 'shipping_test_' . Uuid::fromBytesToHex($id),
            'delivery_time_id' => $this->connection->fetchOne('SELECT `id` FROM `delivery_time` LIMIT 1'),
            'media_id' => $originalMediaId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('app_shipping_method', [
            'id' => Uuid::randomBytes(),
            'app_name' => 'TestApp',
            'identifier' => Uuid::fromBytesToHex($id),
            'shipping_method_id' => $id,
            'original_media_id' => $originalMediaId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function fetchIcon(string $table, string $id): ?string
    {
        $icon = $this->connection->fetchOne(\sprintf('SELECT `icon` FROM `%s` WHERE `id` = :id', $table), ['id' => $id]);

        return \is_string($icon) ? $icon : null;
    }

    /**
     * @return array{?string, ?string} the media of the method and the original media of the app method
     */
    private function fetchMediaIds(string $methodTable, string $methodId): array
    {
        /** @var array{media_id: ?string, original_media_id: ?string} $row */
        $row = $this->connection->fetchAssociative(
            \sprintf(
                'SELECT `method`.`media_id`, `app_method`.`original_media_id`
                FROM `%1$s` `method`
                INNER JOIN `app_%1$s` `app_method` ON `app_method`.`%1$s_id` = `method`.`id`
                WHERE `method`.`id` = :id',
                $methodTable
            ),
            ['id' => $methodId]
        );

        return [$row['media_id'], $row['original_media_id']];
    }

    private static function png(): string
    {
        // 1x1 transparent PNG
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true);
    }
}
