<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
class PaymentMethodLifecycleHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const MANIFEST = __DIR__ . '/../_fixtures/paymentMethodWithIcon/test/manifest.xml';
    private const PAYMENT_IDENTIFIER = 'paymentWithIcon';
    private const MEDIA_FILE_NAME = 'payment_app_test_paymentWithIcon';

    private Connection $connection;

    private AppFixture $appFixture;

    private PaymentMethodLifecycleHandler $handler;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->handler = static::getContainer()->get(PaymentMethodLifecycleHandler::class);

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testUpdateReusesExistingIconMediaWhenOriginalMediaLinkIsMissing(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());

        $this->handler->install(new AppPersistContext($manifest, $app, Context::createDefaultContext(), $appFilesystem, 'en-GB'));

        $mediaIds = $this->getMediaIdsByFileName(self::MEDIA_FILE_NAME);
        static::assertCount(1, $mediaIds, 'Initial import should create exactly one icon media');
        $originalMediaId = $mediaIds[0];

        $this->connection->executeStatement(
            'UPDATE app_payment_method SET original_media_id = NULL WHERE identifier = :identifier',
            ['identifier' => self::PAYMENT_IDENTIFIER]
        );

        $this->handler->update(new AppPersistContext($manifest, $app, Context::createDefaultContext(), $appFilesystem, 'en-GB'));

        $mediaIdsAfter = $this->getMediaIdsByFileName(self::MEDIA_FILE_NAME);
        static::assertCount(1, $mediaIdsAfter, 'Update must reuse the existing icon media instead of creating a duplicate');
        static::assertSame($originalMediaId, $mediaIdsAfter[0]);

        $relinkedMediaId = $this->connection->fetchOne(
            'SELECT original_media_id FROM app_payment_method WHERE identifier = :identifier',
            ['identifier' => self::PAYMENT_IDENTIFIER]
        );
        static::assertIsString($relinkedMediaId);
        static::assertSame($originalMediaId, Uuid::fromBytesToHex($relinkedMediaId), 'Update should relink the reused media');
    }

    /**
     * @return list<string>
     */
    private function getMediaIdsByFileName(string $fileName): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM media WHERE file_name = :fileName',
            ['fileName' => $fileName]
        );

        return array_map(static fn (string $id) => Uuid::fromBytesToHex($id), $ids);
    }
}
