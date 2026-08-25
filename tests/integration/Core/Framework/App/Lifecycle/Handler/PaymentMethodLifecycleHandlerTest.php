<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
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
    private const PAYMENT_IDENTIFIER = 'paymentWithIcon';
    private const MEDIA_FILE_NAME = 'payment_app_test_paymentWithIcon';
    private const PAYMENT_TECHNICAL_NAME = 'payment_test_paymentWithIcon';

    private Connection $connection;

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

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->handler = static::getContainer()->get(PaymentMethodLifecycleHandler::class);
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->appManager = static::getContainer()->get(AppManager::class);

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

        // these are only written by the first time import, so they prove the install branch was taken
        static::assertTrue((bool) $this->connection->fetchOne(
            'SELECT after_order_enabled FROM payment_method WHERE technical_name = :technicalName',
            ['technicalName' => self::PAYMENT_TECHNICAL_NAME]
        ));
        static::assertNotFalse($this->connection->fetchOne(
            'SELECT media_id FROM payment_method WHERE technical_name = :technicalName AND media_id IS NOT NULL',
            ['technicalName' => self::PAYMENT_TECHNICAL_NAME]
        ), 'The manifest icon should be imported on install');

        $paymentMethodId = $this->paymentMethodRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::PAYMENT_TECHNICAL_NAME)),
            $context
        )->firstId();
        static::assertIsString($paymentMethodId);

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

        $paymentMethodId = $this->paymentMethodRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::PAYMENT_TECHNICAL_NAME)),
            $context
        )->firstId();
        static::assertIsString($paymentMethodId);

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

        $paymentMethodId = $this->paymentMethodRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::PAYMENT_TECHNICAL_NAME)),
            $context
        )->firstId();
        static::assertIsString($paymentMethodId);

        $this->paymentMethodRepository->update([[
            'id' => $paymentMethodId,
            'translations' => ['en-GB' => ['name' => 'Mastercard / Visa', 'description' => 'Pay by card']],
        ]], $context);

        // a language the shop gained after the install has no translation for this payment method yet
        $this->connection->executeStatement(
            'DELETE translation FROM payment_method_translation AS translation
             INNER JOIN language ON language.id = translation.language_id
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE translation.payment_method_id = :id AND locale.code = :code',
            ['id' => Uuid::fromHexToBytes($paymentMethodId), 'code' => 'de-DE']
        );
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
        $appId = $this->connection->fetchOne(
            'SELECT app_payment_method.app_id
             FROM app_payment_method
             INNER JOIN payment_method ON payment_method.id = app_payment_method.payment_method_id
             WHERE payment_method.technical_name = :technicalName',
            ['technicalName' => self::PAYMENT_TECHNICAL_NAME]
        );

        return \is_string($appId) ? Uuid::fromBytesToHex($appId) : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function getTranslatedNames(): array
    {
        return $this->getTranslations('name');
    }

    /**
     * @return array<string, string|null>
     */
    private function getTranslatedDescriptions(): array
    {
        return $this->getTranslations('description');
    }

    /**
     * @return array<string, string|null>
     */
    private function getTranslations(string $field): array
    {
        /** @var array<string, string|null> $translations */
        $translations = $this->connection->fetchAllKeyValue(
            \sprintf(
                'SELECT locale.code, translation.%s
                 FROM payment_method_translation AS translation
                 INNER JOIN payment_method ON payment_method.id = translation.payment_method_id
                 INNER JOIN language ON language.id = translation.language_id
                 INNER JOIN locale ON locale.id = language.locale_id
                 WHERE payment_method.technical_name = :technicalName',
                $field
            ),
            ['technicalName' => self::PAYMENT_TECHNICAL_NAME]
        );

        ksort($translations);

        return $translations;
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
