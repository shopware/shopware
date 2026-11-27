<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PaymentMethodPersisterTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private const APP_PATH = __DIR__ . '/../_fixtures/paymentMethodWithIcon/test';
    private const PAYMENT_IDENTIFIER = 'paymentWithIcon';
    private const MEDIA_FILE_NAME = 'payment_app_test_paymentWithIcon';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->removeApp(self::APP_PATH);
    }

    public function testUpdateReusesExistingIconMediaWhenOriginalMediaLinkIsMissing(): void
    {
        $this->installApp(self::APP_PATH);

        $mediaIds = $this->getMediaIdsByFileName();
        static::assertCount(1, $mediaIds);

        $this->connection->executeStatement(
            'UPDATE app_payment_method SET original_media_id = NULL WHERE identifier = :identifier',
            ['identifier' => self::PAYMENT_IDENTIFIER]
        );

        $this->persist();

        static::assertSame($mediaIds, $this->getMediaIdsByFileName());
        static::assertSame($mediaIds[0], $this->getOriginalMediaId());
    }

    public function testReinstallReusesIconMediaOfDeletedPaymentMethod(): void
    {
        $this->installApp(self::APP_PATH);

        $mediaIds = $this->getMediaIdsByFileName();
        static::assertCount(1, $mediaIds);

        $paymentMethodId = $this->getPaymentMethodId();
        static::getContainer()->get('payment_method.repository')->delete([['id' => $paymentMethodId]], Context::createDefaultContext());
        static::assertNull($this->getPaymentMethodId());

        $this->persist();

        static::assertSame($mediaIds, $this->getMediaIdsByFileName());
        static::assertSame($mediaIds[0], $this->getOriginalMediaId());

        $newMediaId = $this->connection->fetchOne(
            'SELECT media_id FROM payment_method WHERE id = :id',
            ['id' => Uuid::fromHexToBytes((string) $this->getPaymentMethodId())]
        );
        static::assertIsString($newMediaId);
        static::assertSame($mediaIds[0], Uuid::fromBytesToHex($newMediaId));
    }

    private function persist(): void
    {
        $persister = static::getContainer()->get(PaymentMethodPersister::class);

        $persister->updatePaymentMethods(
            Manifest::createFromXmlFile(self::APP_PATH . '/manifest.xml'),
            $this->getAppId(),
            Defaults::LANGUAGE_SYSTEM,
            Context::createDefaultContext()
        );
    }

    private function getAppId(): string
    {
        $appId = $this->connection->fetchOne('SELECT `id` FROM `app` WHERE `name` = \'test\'');
        static::assertIsString($appId);

        return Uuid::fromBytesToHex($appId);
    }

    private function getPaymentMethodId(): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM payment_method WHERE technical_name = :name',
            ['name' => 'payment_test_' . self::PAYMENT_IDENTIFIER]
        );

        return \is_string($id) ? Uuid::fromBytesToHex($id) : null;
    }

    private function getOriginalMediaId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT original_media_id FROM app_payment_method WHERE identifier = :identifier',
            ['identifier' => self::PAYMENT_IDENTIFIER]
        );
        static::assertIsString($id);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * @return list<string>
     */
    private function getMediaIdsByFileName(): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM media WHERE file_name = :fileName',
            ['fileName' => self::MEDIA_FILE_NAME]
        );

        return array_map(Uuid::fromBytesToHex(...), $ids);
    }
}
