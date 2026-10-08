<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\ScheduledTask;

use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Tests\Integration\Core\System\Snippet\TranslationClientBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class UpdateTranslationsTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TranslationClientBehaviour;

    /**
     * Pseudo-locale: installing it auto-creates the locale, language and snippet set, so the test
     * needs no pre-existing locale fixture.
     */
    private const PSEUDO_LOCALE = 'ach-UG';

    #[After]
    public function cleanupTranslationFilesystem(): void
    {
        $filesystem = static::getContainer()->get('shopware.filesystem.private');
        static::assertInstanceOf(Filesystem::class, $filesystem);

        if ($filesystem->directoryExists(AbstractTranslationLoader::TRANSLATION_DIR)) {
            $filesystem->deleteDirectory(AbstractTranslationLoader::TRANSLATION_DIR);
        }
    }

    public function testRunMakesNoRemoteRequestWhenNothingInstalled(): void
    {
        // Languages are auto-update-enabled by default, but none of them is linked here, so the task stays offline.
        // The #[After] hook of TranslationClientBehaviour asserts the mock queue is empty, proving no request happened.
        $this->handler()->run();

        static::assertCount(0, $this->getTranslationRequestHandler());
    }

    public function testRunRefreshesInstalledLocaleWhenFlaggedForAutoUpdate(): void
    {
        $this->installLocale();
        // auto-update is enabled by default; make the expectation explicit
        $this->setLanguageAutoUpdate(self::PSEUDO_LOCALE, true);

        $this->appendTranslationResponse($this->metadataResponse('2025-06-01T00:00:00+00:00'));
        $this->appendTranslationFileResponses();

        $this->handler()->run();

        static::assertSame(1, $this->countBaseSnippetSets(self::PSEUDO_LOCALE));
    }

    public function testRunSkipsInstalledLocaleWhenAutoUpdateDisabled(): void
    {
        $this->installLocale();
        $this->setLanguageAutoUpdate(self::PSEUDO_LOCALE, false);

        // The locale is installed but its language opted out of auto-update, so the task must not hit the remote.
        $this->handler()->run();

        static::assertCount(0, $this->getTranslationRequestHandler());
        static::assertSame(1, $this->countBaseSnippetSets(self::PSEUDO_LOCALE));
    }

    private function handler(): UpdateTranslationsTaskHandler
    {
        $handler = static::getContainer()->get(UpdateTranslationsTaskHandler::class);
        static::assertInstanceOf(UpdateTranslationsTaskHandler::class, $handler);

        return $handler;
    }

    private function installLocale(): void
    {
        $this->appendTranslationResponse($this->metadataResponse());
        $this->appendTranslationFileResponses();

        $store = static::getContainer()->get(TranslationMetadataStore::class);
        static::assertInstanceOf(TranslationMetadataStore::class, $store);
        $updater = static::getContainer()->get(TranslationUpdater::class);
        static::assertInstanceOf(TranslationUpdater::class, $updater);

        $metadata = $store->getUpdatedLocalMetadata([self::PSEUDO_LOCALE]);
        $updater->install($updater->planInstall([self::PSEUDO_LOCALE], $metadata), Context::createCLIContext());
        $store->save($metadata);
    }

    private function setLanguageAutoUpdate(string $localeCode, bool $enabled): void
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $localeCode));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        $repository->update([['id' => $id, 'translationAutoUpdate' => $enabled]], Context::createDefaultContext());
    }

    private function metadataResponse(string $updatedAt = '2025-01-01T00:00:00+00:00'): Response
    {
        $body = json_encode([
            ['locale' => self::PSEUDO_LOCALE, 'updatedAt' => $updatedAt, 'progress' => 100],
        ], \JSON_THROW_ON_ERROR);

        return new Response(200, [], $body);
    }

    private function countBaseSnippetSets(string $locale): int
    {
        /** @var EntityRepository<SnippetSetCollection> $repository */
        $repository = static::getContainer()->get('snippet_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $locale));
        $criteria->addFilter(new EqualsFilter('name', "BASE $locale"));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getTotal();
    }
}
