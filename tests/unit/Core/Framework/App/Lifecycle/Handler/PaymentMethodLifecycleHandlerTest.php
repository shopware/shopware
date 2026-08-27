<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMethodLifecycleHandler::class)]
class PaymentMethodLifecycleHandlerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentMethodCollection>
     */
    private StaticEntityRepository $paymentMethodRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private PaymentMethodLifecycleHandler $persister;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new StaticEntityRepository([]);
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->persister = new PaymentMethodLifecycleHandler(
            $this->paymentMethodRepository,
            $this->mediaRepository,
            static::createStub(MediaService::class),
        );
    }

    public function testPersistUpsertsConfiguredPaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection());

        $this->persister->install($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(2, $paymentMethods);
        $this->assertPaymentMethodPayload($paymentMethods[0], $appId, 'paymentMethodOne');
        $this->assertPaymentMethodPayload($paymentMethods[1], $appId, 'paymentMethodTwo');
        static::assertSame('https://payment.example.com/pay', $paymentMethods[0]['appPaymentMethod']['payUrl']);
        static::assertSame('https://payment.example.com/finalize', $paymentMethods[0]['appPaymentMethod']['finalizeUrl']);

        static::assertSame(
            ['en-GB' => 'First Payment Method', 'de-DE' => 'Erste Zahlungsart'],
            $paymentMethods[0]['name'],
            'A first time import must write the manifest names for every locale'
        );
        static::assertSame(
            ['en-GB' => 'This is a simple description', 'de-DE' => 'Das ist eine einfache Beschreibung'],
            $paymentMethods[0]['description']
        );
        static::assertSame(['en-GB' => 'Second Payment Method'], $paymentMethods[1]['name']);
        static::assertSame(['en-GB' => 'This is another simple description'], $paymentMethods[1]['description']);
    }

    public function testUpdateKeepsTextsTheMerchantChanged(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        // the shop shows something else than the manifest last shipped, so the merchant renamed it
        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection([
            $this->buildExistingPaymentMethod(
                $appId,
                'paymentMethodOne',
                ['en-GB' => 'Mastercard / Visa', 'de-DE' => 'Mastercard / Visa'],
            ),
        ]));

        $this->persister->update($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(2, $paymentMethods);
        static::assertArrayNotHasKey('name', $paymentMethods[0]);
    }

    public function testUpdateAddsTheManifestTextsForLanguagesWithoutATranslation(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        // the shop was set up in English and got German later, so only the English translation exists
        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection([
            $this->buildExistingPaymentMethod(
                $appId,
                'paymentMethodOne',
                ['en-GB' => 'Mastercard / Visa'],
            ),
        ]));

        $this->persister->update($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertSame(['de-DE' => 'Erste Zahlungsart'], $paymentMethods[0]['name']);
        static::assertSame(
            ['de-DE' => 'Das ist eine einfache Beschreibung'],
            $paymentMethods[0]['description'],
            'The English translation already exists, so it belongs to the merchant'
        );
    }

    public function testUpdateComparesAgainstTheTranslationCodeAndNotTheFormattingLocale(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        // the stub languages format as en-US while translating as en-GB and de-DE
        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection([
            $this->buildExistingPaymentMethod(
                $appId,
                'paymentMethodOne',
                ['en-GB' => 'Mastercard / Visa', 'de-DE' => 'Mastercard / Visa'],
            ),
        ]));

        $this->persister->update($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertArrayNotHasKey('name', $paymentMethods[0], 'The existing translations must be matched by translation code');
    }

    public function testUpdateStillWritesTheManifestControlledFields(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');
        $existing = $this->buildExistingPaymentMethod($appId, 'paymentMethodOne', ['en-GB' => 'Mastercard / Visa']);

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection([$existing]));

        $this->persister->update($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertSame($existing->getId(), $paymentMethods[0]['id']);
        static::assertSame('app\\paymentPersister_paymentMethodOne', $paymentMethods[0]['handlerIdentifier']);
        static::assertSame('payment_paymentPersister_paymentMethodOne', $paymentMethods[0]['technicalName']);
        static::assertIsArray($paymentMethods[0]['appPaymentMethod']);
        static::assertSame('https://payment.example.com/pay', $paymentMethods[0]['appPaymentMethod']['payUrl']);
        static::assertArrayNotHasKey('afterOrderEnabled', $paymentMethods[0], 'The merchant owns this flag after the initial import');
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

        $persister = new PaymentMethodLifecycleHandler($this->paymentMethodRepository, $this->mediaRepository, $mediaService);

        $persister->install($this->buildContext($manifest, $appId, new StaticFilesystem(['icon.png' => $png])));

        $payloads = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(1, $payloads);
        static::assertIsArray($payloads[0]['appPaymentMethod']);
        static::assertSame($existingMediaId, $payloads[0]['appPaymentMethod']['originalMediaId']);
    }

    public function testActivateUpdatesInactivePaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $paymentMethodIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->paymentMethodRepository->addSearch($paymentMethodIds);

        $this->persister->activate(new AppActivationContext($this->buildApp($appId), $context));

        static::assertSame([
            ['id' => $paymentMethodIds[0], 'active' => true],
            ['id' => $paymentMethodIds[1], 'active' => true],
        ], $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActivePaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $paymentMethodIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->paymentMethodRepository->addSearch($paymentMethodIds);

        $this->persister->deactivate(new AppActivationContext($this->buildApp($appId), $context));

        static::assertSame([
            ['id' => $paymentMethodIds[0], 'active' => false],
            ['id' => $paymentMethodIds[1], 'active' => false],
        ], $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    private function buildContext(Manifest $manifest, string $appId, ?Filesystem $appFilesystem = null): AppPersistContext
    {
        $app = $this->buildApp($appId);
        $app->setActive(true);
        $app->setAppSecret('test-secret');

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $appFilesystem ?? new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    private function buildApp(string $appId): AppEntity
    {
        $app = new AppEntity();
        $app->setId($appId);

        return $app;
    }

    /**
     * @param array<string, string> $currentNames texts the shop has right now, keyed by translation code
     */
    private function buildExistingPaymentMethod(string $appId, string $identifier, array $currentNames = []): PaymentMethodEntity
    {
        $appPaymentMethod = new AppPaymentMethodEntity();
        $appPaymentMethod->setUniqueIdentifier(Uuid::randomHex());
        $appPaymentMethod->setId(Uuid::randomHex());
        $appPaymentMethod->setAppId($appId);
        $appPaymentMethod->setAppName('paymentPersister');
        $appPaymentMethod->setIdentifier($identifier);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setHandlerIdentifier(\sprintf('app\\paymentPersister_%s', $identifier));
        $paymentMethod->setAppPaymentMethod($appPaymentMethod);
        $paymentMethod->setTranslations(new PaymentMethodTranslationCollection(
            array_map($this->buildTranslation(...), array_keys($currentNames), $currentNames)
        ));

        return $paymentMethod;
    }

    private function buildTranslation(string $translationCode, string $name): PaymentMethodTranslationEntity
    {
        $locale = new LocaleEntity();
        $locale->setUniqueIdentifier(Uuid::randomHex());
        $locale->setId(Uuid::randomHex());
        $locale->setCode($translationCode);

        // a shop can format in one locale and translate in another, the payload keys follow the translation code
        $formattingLocale = new LocaleEntity();
        $formattingLocale->setUniqueIdentifier(Uuid::randomHex());
        $formattingLocale->setId(Uuid::randomHex());
        $formattingLocale->setCode('en-US');

        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $language->setId(Uuid::randomHex());
        $language->setLocale($formattingLocale);
        $language->setTranslationCode($locale);

        $translation = new PaymentMethodTranslationEntity();
        $translation->setUniqueIdentifier(Uuid::randomHex());
        $translation->setLanguageId($language->getId());
        $translation->setLanguage($language);
        $translation->setName($name);

        return $translation;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertPaymentMethodPayload(array $payload, string $appId, string $identifier): void
    {
        static::assertSame(\sprintf('app\\paymentPersister_%s', $identifier), $payload['handlerIdentifier']);
        static::assertSame(\sprintf('payment_paymentPersister_%s', $identifier), $payload['technicalName']);
        static::assertTrue($payload['afterOrderEnabled']);
        static::assertIsArray($payload['appPaymentMethod']);
        static::assertSame($identifier, $payload['appPaymentMethod']['identifier']);
        static::assertSame($appId, $payload['appPaymentMethod']['appId']);
        static::assertSame('paymentPersister', $payload['appPaymentMethod']['appName']);
    }
}
