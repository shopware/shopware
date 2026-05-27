<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(PaymentMethodPersister::class)]
class PaymentMethodPersisterTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentMethodCollection>
     */
    private StaticEntityRepository $paymentMethodRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new StaticEntityRepository([]);
        $this->mediaRepository = new StaticEntityRepository([]);
    }

    public function testPersistReusesExistingMediaByFileNameWhenOriginalMediaLinkIsMissing(): void
    {
        $appId = Uuid::randomHex();
        $existingMediaId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_method_with_icon.xml');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        static::assertIsString($png);

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection());
        $this->mediaRepository->addSearch([$existingMediaId]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::anything(),
                'png',
                static::anything(),
                'payment_app_paymentPersister_paymentWithIcon',
                static::anything(),
                PaymentMethodDefinition::ENTITY_NAME,
                $existingMediaId,
                false
            )
            ->willReturn($existingMediaId);

        $persister = new PaymentMethodPersister($this->paymentMethodRepository, $this->mediaRepository, $mediaService);

        $app = new AppEntity();
        $app->setId($appId);
        $app->setActive(true);
        $app->setAppSecret('test-secret');

        $persister->persist(new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(['icon.png' => $png]),
            defaultLocale: 'en-GB',
            isInstall: true,
        ));

        static::assertCount(1, $this->paymentMethodRepository->upserts);
        static::assertCount(1, $this->paymentMethodRepository->upserts[0]);
        static::assertIsArray($this->paymentMethodRepository->upserts[0][0]['appPaymentMethod']);
        static::assertSame($existingMediaId, $this->paymentMethodRepository->upserts[0][0]['appPaymentMethod']['originalMediaId']);
    }
}
