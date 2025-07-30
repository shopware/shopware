<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Framework\App\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class SystemLanguageChangedSubscriberTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    /**
     * @var EntityRepository<AppAdministrationSnippetCollection>
     */
    private EntityRepository $snippetRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->snippetRepository = $this->getContainer()->get('app_administration_snippet.repository');
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsAfterSystemLanguageChanged(string $localeCode): void
    {
        $previousSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('en-GB', $previousSystemLocale['code']);

        $appOne = $this->createAppWithSnippets('SwagAppOne', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppTwo');
        $appThree = $this->createAppWithSnippets('SwagAppThree', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppFour');

        $snippetsBefore = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsBefore);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appThree->getId(), $previousSystemLocale['id']);

        $previousLocaleCode = '';
        $newLocaleCode = '';
        $this->getContainer()->get('event_dispatcher')->addListener(
            SystemLanguageChangeEvent::class,
            static function (SystemLanguageChangeEvent $event) use (&$previousLocaleCode, &$newLocaleCode): void {
                $previousLocaleCode = $event->previousLocaleCode;
                $newLocaleCode = $event->newLocaleCode;
            }
        );

        $this->getContainer()->get(ShopConfigurator::class)->setDefaultLanguage($localeCode);

        static::assertSame('en-GB', $previousLocaleCode);
        static::assertSame($localeCode, $newLocaleCode);

        $previousLocale = $this->getLocale($previousLocaleCode);

        $snippetsAfter = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsAfter);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appOne->getId(), $previousLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appThree->getId(), $previousLocale['id']);
    }

    public function testDoesNoUpdateSnippetsAfterSystemLanguageChangedFromEnGbToDeDe(): void
    {
        $previousSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('en-GB', $previousSystemLocale['code']);

        $appOne = $this->createAppWithSnippets('SwagAppOne', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppTwo');
        $appThree = $this->createAppWithSnippets('SwagAppThree', $previousSystemLocale['id']);
        $this->createAppWithSnippets('SwagAppFour');

        $snippetsBefore = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsBefore);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsBefore, $appThree->getId(), $previousSystemLocale['id']);

        $this->getContainer()->get(ShopConfigurator::class)->setDefaultLanguage('de-DE');

        $newSystemLocale = $this->getCurrentSystemLocale();
        static::assertSame('de-DE', $newSystemLocale['code']);

        $snippetsAfter = $this->snippetRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $snippetsAfter);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appOne->getId(), $previousSystemLocale['id']);
        self::assertSnippetExistsForAppAndLocale($snippetsAfter, $appThree->getId(), $previousSystemLocale['id']);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    /**
     * @return array{code: string, id: string}
     */
    private function getCurrentSystemLocale(): array
    {
        $currentSystemLocale = $this->connection
            ->executeQuery(
                'SELECT locale.code, LOWER(HEX(locale.id)) AS id FROM locale INNER JOIN language ON language.locale_id = locale.id WHERE language.id = :languageId',
                ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            )->fetchAssociative();

        if ($currentSystemLocale === false) {
            static::fail('Could not fetch current system locale');
        }

        /** @var array{code: string, id: string} $currentSystemLocale */
        return $currentSystemLocale;
    }

    /**
     * @return array{code: string, id: string}
     */
    private function getLocale(string $code): array
    {
        $locale = $this->connection
            ->executeQuery(
                'SELECT code, LOWER(HEX(locale.id)) AS id FROM locale WHERE code = :code',
                ['code' => $code]
            )->fetchAssociative();

        if ($locale === false) {
            static::fail(\sprintf('Could not fetch locale with code "%s"', $code));
        }

        /** @var array{code: string, id: string} $locale */
        return $locale;
    }

    private function createAppWithSnippets(string $name, ?string $localeId = null): AppEntity
    {
        $this->appRepository->create([
            [
                'id' => $id = Uuid::randomHex(),
                'name' => $name,
                'active' => true,
                'appVersion' => '1.0.0',
                'author' => 'Shopware AG',
                'label' => [
                    'en-GB' => 'Test App',
                    'en-US' => 'Test App',
                    'de-DE' => 'Test App',
                ],
                'path' => 'path',
                'version' => '1.0.0',
                'integration' => [
                    'id' => Uuid::randomHex(),
                    'label' => $name . ' Integration',
                    'accessKey' => Uuid::randomHex(),
                    'secretAccessKey' => Uuid::randomHex(),
                ],
                'aclRole' => [
                    'id' => Uuid::randomHex(),
                    'name' => $name . ' ACL Role',
                ],
            ],
        ], $this->context);

        if ($localeId !== null) {
            $this->snippetRepository->create([
                [
                    'appId' => $id,
                    'localeId' => $localeId,
                    'value' => json_encode([]),
                ],
            ], $this->context);
        }

        $app = $this->appRepository->search(new Criteria([$id]), $this->context)->first();
        \assert($app instanceof AppEntity);

        return $app;
    }

    private static function assertSnippetExistsForAppAndLocale(
        AppAdministrationSnippetCollection $snippets,
        string $appId,
        string $localeId
    ): void {
        static::assertInstanceOf(
            AppAdministrationSnippetEntity::class,
            $snippets->filter(fn (AppAdministrationSnippetEntity $snippet) => $snippet->getAppId() === $appId && $snippet->getLocaleId() === $localeId)->first(),
        );
    }
}
