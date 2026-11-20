<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(PaymentMethodPersister::class)]
class PaymentMethodPersisterTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function testUpdateReusesExistingMediaByFileNameWhenNoOriginalMediaIsLinked(): void
    {
        $existingMediaId = Uuid::randomHex();

        /** @var StaticEntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = new StaticEntityRepository([new PaymentMethodCollection()]);
        /** @var StaticEntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = new StaticEntityRepository([[$existingMediaId]]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::anything(),
                'png',
                'image/png',
                'payment_app_paymentPersister_paymentWithIcon',
                static::anything(),
                PaymentMethodDefinition::ENTITY_NAME,
                $existingMediaId,
                false
            )
            ->willReturn($existingMediaId);

        $this->updatePaymentMethods($paymentMethodRepository, $mediaRepository, $mediaService);

        $payload = $paymentMethodRepository->upserts[0][0];
        static::assertSame($existingMediaId, $payload['appPaymentMethod']['originalMediaId']);
        static::assertSame($existingMediaId, $payload['mediaId']);
    }

    public function testUpdateCreatesNewMediaWhenNoMediaWithTheFileNameExists(): void
    {
        $newMediaId = Uuid::randomHex();

        /** @var StaticEntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = new StaticEntityRepository([new PaymentMethodCollection()]);
        /** @var StaticEntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = new StaticEntityRepository([[]]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::anything(),
                'png',
                'image/png',
                'payment_app_paymentPersister_paymentWithIcon',
                static::anything(),
                PaymentMethodDefinition::ENTITY_NAME,
                null,
                false
            )
            ->willReturn($newMediaId);

        $this->updatePaymentMethods($paymentMethodRepository, $mediaRepository, $mediaService);

        static::assertSame($newMediaId, $paymentMethodRepository->upserts[0][0]['appPaymentMethod']['originalMediaId']);
    }

    /**
     * @param StaticEntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param StaticEntityRepository<MediaCollection> $mediaRepository
     */
    private function updatePaymentMethods(
        StaticEntityRepository $paymentMethodRepository,
        StaticEntityRepository $mediaRepository,
        MediaService $mediaService,
    ): void {
        $png = base64_decode(self::PNG, true);
        static::assertIsString($png);

        $persister = new PaymentMethodPersister(
            $paymentMethodRepository,
            $mediaRepository,
            $mediaService,
            new StaticSourceResolver(['paymentPersister' => new StaticFilesystem(['icon.png' => $png])]),
        );

        $persister->updatePaymentMethods(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_method_with_icon.xml'),
            Uuid::randomHex(),
            'en-GB',
            Context::createDefaultContext(),
        );
    }
}
