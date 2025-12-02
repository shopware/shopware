<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleCollection;

/**
 * @internal
 */
#[Package('discovery')]
readonly class AppAdministrationSnippetPersister
{
    /**
     * @param EntityRepository<AppAdministrationSnippetCollection> $appAdministrationSnippetRepository
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(
        private EntityRepository $appAdministrationSnippetRepository,
        private EntityRepository $localeRepository,
        private CacheInvalidator $cacheInvalidator
    ) {
    }

    /**
     * @param array<string, string> $snippets
     */
    public function updateSnippets(AppEntity $app, array $snippets, Context $context): void
    {
        $newOrUpdatedSnippets = [];
        $existingAppSnippets = $this->getExistingAppSnippets($app->getId(), $context);
        $coreSnippets = $this->getCoreAdministrationSnippets();

        $firstLevelSnippetKeys = [];
        foreach ($snippets as $snippetString) {
            $decodedSnippets = json_decode($snippetString, true, 512, \JSON_THROW_ON_ERROR);
            $firstLevelSnippetKeys = array_keys($decodedSnippets);
        }

        if ($duplicatedKeys = array_values(array_intersect(array_keys($coreSnippets), $firstLevelSnippetKeys))) {
            throw SnippetException::extendOrOverwriteCore($duplicatedKeys);
        }

        // only throw exception if snippets are given but not en-GB
        if (!\array_key_exists('en-GB', $snippets) && !empty($snippets)) {
            throw SnippetException::defaultLanguageNotGiven('en-GB');
        }

        $localeCodeToIdMapping = $this->mapLocaleCodesToIds(array_keys($snippets), $context);

        $existingLocales = [];
        foreach ($existingAppSnippets as $snippetEntity) {
            $existingLocales[$snippetEntity->getLocaleId()] = $snippetEntity->getId();
        }

        foreach ($snippets as $snippetLocale => $snippet) {
            if (!\array_key_exists($snippetLocale, $localeCodeToIdMapping)) {
                // The locale for the given snippet does not exist.
                continue;
            }

            $localeId = $localeCodeToIdMapping[$snippetLocale];
            $id = Uuid::randomHex();

            if (\array_key_exists($localeId, $existingLocales)) {
                $id = $existingLocales[$localeId];
                unset($existingLocales[$localeId]);
            }

            $newOrUpdatedSnippets[] = [
                'id' => $id,
                'value' => $snippet,
                'appId' => $app->getId(),
                'localeId' => $localeCodeToIdMapping[$snippetLocale],
            ];
        }

        $this->appAdministrationSnippetRepository->upsert($newOrUpdatedSnippets, $context);

        // if locale is given --> upsert, if not given --> delete
        $deletedIds = array_values($existingLocales);
        $this->deleteSnippets($deletedIds, $context);

        $this->cacheInvalidator->invalidate([CachedSnippetFinder::CACHE_TAG], true);
    }

    private function getExistingAppSnippets(string $appId, Context $context): AppAdministrationSnippetCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        return $this->appAdministrationSnippetRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return array<string, mixed>
     */
    private function getCoreAdministrationSnippets(): array
    {
        $path = __DIR__ . '/../Resources/app/administration/src/app/snippet/en.json';
        $snippets = file_get_contents($path);

        if (!$snippets) {
            return [];
        }

        return json_decode($snippets, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $ids
     */
    private function deleteSnippets(array $ids, Context $context): void
    {
        $data = [];
        foreach ($ids as $id) {
            $data[] = ['id' => $id];
        }

        $this->appAdministrationSnippetRepository->delete($data, $context);
    }

    /**
     * @param list<string> $localeCodes
     *
     * @return array<string, string>
     */
    private function mapLocaleCodesToIds(array $localeCodes, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('code', $localeCodes));
        $criteria->addFields(['id', 'code']);

        $locales = $this->localeRepository->search($criteria, $context)->getEntities()->getElements();

        return array_column($locales, 'id', 'code');
    }
}
