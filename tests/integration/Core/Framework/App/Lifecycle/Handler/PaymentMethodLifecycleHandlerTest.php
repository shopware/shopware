<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentMethodLifecycleHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const MANIFEST = __DIR__ . '/../_fixtures/paymentMethodWithIcon/test/manifest.xml';
    private const MEDIA_FILE_NAME = 'payment_app_test_paymentWithIcon';
    private const PAYMENT_TECHNICAL_NAME = 'payment_test_paymentWithIcon';

    private AppFixture $appFixture;

    private PaymentMethodLifecycleHandler $handler;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppManager $appManager;

    /**
     * @var EntityRepository<EntityCollection<PaymentMethodTranslationEntity>>
     */
    private EntityRepository $paymentMethodTranslationRepository;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    /**
     * @var EntityRepository<EntityCollection<AppPaymentMethodEntity>>
     */
    private EntityRepository $appPaymentMethodRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(PaymentMethodLifecycleHandler::class);
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->appManager = static::getContainer()->get(AppManager::class);
        $this->paymentMethodTranslationRepository = static::getContainer()->get('payment_method_translation.repository');
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->appPaymentMethodRepository = static::getContainer()->get('app_payment_method.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testUpdateReusesExistingIconMediaWhenOriginalMediaLinkIsMissing(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());
        $context = Context::createDefaultContext();

        $this->handler->install(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        $mediaIds = $this->getMediaIdsByFileName(self::MEDIA_FILE_NAME, $context);
        static::assertCount(1, $mediaIds, 'Initial import should create exactly one icon media');
        $originalMediaId = $mediaIds[0];

        $appPaymentMethod = $this->getPaymentMethod($context)->getAppPaymentMethod();
        static::assertNotNull($appPaymentMethod);
        $this->appPaymentMethodRepository->update([['id' => $appPaymentMethod->getId(), 'originalMediaId' => null]], $context);

        $this->handler->update(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        $mediaIdsAfter = $this->getMediaIdsByFileName(self::MEDIA_FILE_NAME, $context);
        static::assertCount(1, $mediaIdsAfter, 'Update must reuse the existing icon media instead of creating a duplicate');
        static::assertSame($originalMediaId, $mediaIdsAfter[0]);

        static::assertSame(
            $originalMediaId,
            $this->getPaymentMethod($context)->getAppPaymentMethod()?->getOriginalMediaId(),
            'Update should relink the reused media'
        );
    }

    public function testUpdateKeepsMerchantCustomizedNameAndDescription(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());
        $context = Context::createDefaultContext();

        $this->handler->install(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        static::assertSame(
            ['de-DE' => 'Die App-Zahlungsart', 'en-GB' => 'The app payment method'],
            $this->getTranslatedNames(),
            'The manifest names should be imported on install'
        );
        static::assertSame(
            ['de-DE' => 'Das ist eine Beschreibung', 'en-GB' => 'This is a description'],
            $this->getTranslatedDescriptions()
        );

        $paymentMethod = $this->getPaymentMethod($context);
        $paymentMethodId = $paymentMethod->getId();

        // these are only written by the first time import, so they prove the install branch was taken
        static::assertTrue($paymentMethod->getAfterOrderEnabled());
        static::assertNotNull($paymentMethod->getMediaId(), 'The manifest icon should be imported on install');

        $this->paymentMethodRepository->update([[
            'id' => $paymentMethodId,
            'translations' => [
                'en-GB' => ['name' => 'Mastercard / Visa', 'description' => 'Pay by card'],
                'de-DE' => ['name' => 'Mastercard / Visa', 'description' => 'Mit Karte bezahlen'],
            ],
        ]], $context);

        $this->handler->update(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        static::assertSame(
            ['de-DE' => 'Mastercard / Visa', 'en-GB' => 'Mastercard / Visa'],
            $this->getTranslatedNames(),
            'An app update must not reset the merchant customized name'
        );
        static::assertSame(
            ['de-DE' => 'Mit Karte bezahlen', 'en-GB' => 'Pay by card'],
            $this->getTranslatedDescriptions(),
            'An app update must not reset the merchant customized description'
        );
    }

    public function testInstallAfterUninstallKeepsMerchantCustomizedTextsAndRelinksTheApp(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());
        $context = Context::createDefaultContext();

        $this->handler->install(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        static::assertSame(
            ['de-DE' => 'Die App-Zahlungsart', 'en-GB' => 'The app payment method'],
            $this->getTranslatedNames(),
            'The first install must import the manifest names, otherwise the assertions below pass vacuously'
        );

        $paymentMethodId = $this->getPaymentMethod($context)->getId();

        $this->paymentMethodRepository->update([[
            'id' => $paymentMethodId,
            'translations' => [
                'en-GB' => ['name' => 'Mastercard / Visa', 'description' => 'Pay by card'],
                'de-DE' => ['name' => 'Mastercard / Visa', 'description' => 'Mit Karte bezahlen'],
            ],
        ]], $context);

        // uninstalling keeps the payment method and only detaches the app, see the ON DELETE SET NULL on app_payment_method.app_id
        $this->appManager->uninstall($this->appFixture->getApp($app->getId()), $context);

        static::assertCount(0, $this->appRepository->searchIds(new Criteria([$app->getId()]), $context)->getIds());
        static::assertNull($this->getLinkedAppId(), 'Uninstalling the app should detach, not delete, the app payment method');

        $reinstalledApp = $this->appFixture->createApp($manifest);
        $this->handler->install(new AppPersistContext($manifest, $reinstalledApp, $context, $appFilesystem, 'en-GB'));

        static::assertSame(
            ['de-DE' => 'Mastercard / Visa', 'en-GB' => 'Mastercard / Visa'],
            $this->getTranslatedNames(),
            'Reinstalling keeps the merchant texts, just like it keeps a replaced icon'
        );
        static::assertSame(
            ['de-DE' => 'Mit Karte bezahlen', 'en-GB' => 'Pay by card'],
            $this->getTranslatedDescriptions()
        );
        static::assertSame($reinstalledApp->getId(), $this->getLinkedAppId(), 'Reinstalling should relink the payment method to the new app');
    }

    public function testUpdateFillsTheManifestTextsForALanguageWithoutATranslation(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appFilesystem = new Filesystem($manifest->getPath());
        $context = Context::createDefaultContext();

        $this->handler->install(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        $paymentMethodId = $this->getPaymentMethod($context)->getId();

        $this->paymentMethodRepository->update([[
            'id' => $paymentMethodId,
            'translations' => ['en-GB' => ['name' => 'Mastercard / Visa', 'description' => 'Pay by card']],
        ]], $context);

        // a language the shop gained after the install has no translation for this payment method yet
        $this->paymentMethodTranslationRepository->delete([[
            'paymentMethodId' => $paymentMethodId,
            'languageId' => $this->getLanguageId('de-DE', $context),
        ]], $context);
        static::assertSame(['en-GB' => 'Mastercard / Visa'], $this->getTranslatedNames());

        $this->handler->update(new AppPersistContext($manifest, $app, $context, $appFilesystem, 'en-GB'));

        static::assertSame(
            ['de-DE' => 'Die App-Zahlungsart', 'en-GB' => 'Mastercard / Visa'],
            $this->getTranslatedNames(),
            'The untranslated language gets the manifest name while the customized one is kept'
        );
        static::assertSame(
            ['de-DE' => 'Das ist eine Beschreibung', 'en-GB' => 'Pay by card'],
            $this->getTranslatedDescriptions()
        );
    }

    private function getLinkedAppId(): ?string
    {
        return $this->getPaymentMethod(Context::createDefaultContext())->getAppPaymentMethod()?->getAppId();
    }

    private function getPaymentMethod(Context $context): PaymentMethodEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', self::PAYMENT_TECHNICAL_NAME));
        $criteria->addAssociation('appPaymentMethod');
        $criteria->addAssociation('translations.language.translationCode');

        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($paymentMethod);

        return $paymentMethod;
    }

    /**
     * @return array<string, string|null>
     */
    private function getTranslatedNames(): array
    {
        return $this->getTranslations(static fn (PaymentMethodTranslationEntity $translation) => $translation->getName());
    }

    /**
     * @return array<string, string|null>
     */
    private function getTranslatedDescriptions(): array
    {
        return $this->getTranslations(static fn (PaymentMethodTranslationEntity $translation) => $translation->getDescription());
    }

    /**
     * @param \Closure(PaymentMethodTranslationEntity): ?string $text
     *
     * @return array<string, string|null>
     */
    private function getTranslations(\Closure $text): array
    {
        $translations = [];
        foreach ($this->getPaymentMethod(Context::createDefaultContext())->getTranslations() ?? [] as $translation) {
            $translationCode = $translation->getLanguage()?->getTranslationCode()?->getCode();
            static::assertIsString($translationCode);

            $translations[$translationCode] = $text($translation);
        }

        ksort($translations);

        return $translations;
    }

    private function getLanguageId(string $translationCode, Context $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('translationCode.code', $translationCode));

        $languageId = $this->languageRepository->searchIds($criteria, $context)->firstId();
        static::assertIsString($languageId);

        return $languageId;
    }

    /**
     * @return list<string>
     */
    private function getMediaIdsByFileName(string $fileName, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('fileName', $fileName));

        return array_values($this->mediaRepository->searchIds($criteria, $context)->getIds());
    }
}
