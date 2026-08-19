<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Subscriber;

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
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Tests\Integration\Core\System\Snippet\TranslationClientBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class LanguageDeletionSubscriberTest extends TestCase
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

    public function testDeletingALanguageDropsItsCommunityTranslationMetadata(): void
    {
        $this->installLocale();

        $store = $this->metadataStore();
        static::assertTrue($store->getLocalMetadata()->has(self::PSEUDO_LOCALE));

        $this->deleteLanguage(self::PSEUDO_LOCALE);

        // once the language is gone, the lock must no longer mark the locale as installed
        static::assertFalse($store->getLocalMetadata()->has(self::PSEUDO_LOCALE));
    }

    private function installLocale(): void
    {
        $this->appendTranslationResponse($this->metadataResponse());
        $this->appendTranslationFileResponses();

        $store = $this->metadataStore();
        $updater = static::getContainer()->get(TranslationUpdater::class);
        static::assertInstanceOf(TranslationUpdater::class, $updater);

        $updater->update($store->getUpdatedLocalMetadata([self::PSEUDO_LOCALE]), Context::createCLIContext());
    }

    private function deleteLanguage(string $localeCode): void
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $localeCode));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        $repository->delete([['id' => $id]], Context::createDefaultContext());
    }

    private function metadataStore(): TranslationMetadataStore
    {
        $store = static::getContainer()->get(TranslationMetadataStore::class);
        static::assertInstanceOf(TranslationMetadataStore::class, $store);

        return $store;
    }

    private function metadataResponse(): Response
    {
        $body = json_encode([
            ['locale' => self::PSEUDO_LOCALE, 'updatedAt' => '2025-01-01T00:00:00+00:00', 'progress' => 100],
        ], \JSON_THROW_ON_ERROR);

        return new Response(200, [], $body);
    }
}
