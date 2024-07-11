<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @phpstan-type Snippet array{value: string, origin: string, resetTo: string, translationKey: string, author: string, id: string|null, setId: string}
 * @phpstan-type SnippetArray array<string, array{snippets: array<string, Snippet>}>
 * @phpstan-type SnippetFilter array{edited?: true, added?: true, empty?: true, author?: list<string>, namespace?: list<string>, term?: string}
 * @phpstan-type SnippetSort array{sortBy: string, sortDirection: string}|array{}
 */
#[Package('services-settings')]
class SnippetService
{
    /**
     * @internal
     *
     * @param EntityRepository<SnippetCollection> $snippetRepository
     * @param EntityRepository<SnippetSetCollection> $snippetSetRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SnippetFileCollection $snippetFileCollection,
        private readonly EntityRepository $snippetRepository,
        private readonly EntityRepository $snippetSetRepository,
        private readonly SnippetFilterFactory $snippetFilterFactory,
        /**
         * The "kernel" service is synthetic, it needs to be set at boot time before it can be used.
         * We need to get StorefrontPluginRegistry service from service_container lazily because it depends on kernel service.
         */
        private readonly ContainerInterface $container,
        private readonly ?DatabaseSalesChannelThemeLoader $salesChannelThemeLoader = null
    ) {
    }

    /**
     * @param int<1, max> $limit
     * @param SnippetFilter $requestFilters
     * @param SnippetSort $sort
     *
     * @return array{total:int, data: array<string, list<Snippet>>}
     */
    public function getList(int $page, int $limit, Context $context, array $requestFilters, array $sort): array
    {
        --$page;
        $isoList = $this->createIsoList($context);
        $languageFiles = $this->getSnippetFilesByIso($isoList);

        $fileSnippets = $this->getFileSnippets($languageFiles, $isoList);
        $dbSnippets = $this->databaseSnippetsToArray($this->findSnippetInDatabase(new Criteria(), $context), $fileSnippets);

        $snippets = array_replace_recursive($fileSnippets, $dbSnippets);
        $snippets = $this->fillBlankSnippets($snippets, $isoList);

        foreach ($requestFilters as $requestFilterName => $requestFilterValue) {
            $snippets = $this->snippetFilterFactory->getFilter($requestFilterName)->filter($snippets, $requestFilterValue);
        }

        $snippets = $this->sortSnippets($sort, $snippets);

        $total = 0;
        foreach ($snippets as &$set) {
            $total = $total > 0 ? $total : \count($set['snippets']);
            $set['snippets'] = array_chunk($set['snippets'], $limit, true)[$page] ?? [];
        }

        return [
            'total' => $total,
            'data' => $this->mergeSnippetsComparison($snippets),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId, ?string $fallbackLocale = null, ?string $salesChannelId = null): array
    {
        $locale = $this->getLocaleBySnippetSetId($snippetSetId);

        $snippets = [];

        $snippetFileCollection = $this->snippetFileCollection;

        $usingThemes = $this->getUsedThemes($salesChannelId);
        $unusedThemes = $this->getUnusedThemes($usingThemes);
        $snippetCollection = $snippetFileCollection->filter(fn (AbstractSnippetFile $snippetFile) => !\in_array($snippetFile->getTechnicalName(), $unusedThemes, true));

        $fallbackSnippets = [];

        if ($fallbackLocale !== null) {
            // fallback has to be the base
            $snippets = $fallbackSnippets = $this->getSnippetsByLocale($snippetCollection, $fallbackLocale);
        }

        // now override fallback with defaults in catalog
        $snippets = array_replace_recursive(
            $snippets,
            $catalog->all('messages')
        );

        // after fallback and default catalog merged, overwrite them with current locale snippets
        $snippets = array_replace_recursive(
            $snippets,
            $locale === $fallbackLocale ? $fallbackSnippets : $this->getSnippetsByLocale($snippetCollection, $locale)
        );

        // at least overwrite the snippets with the database customer overwrites
        return array_replace_recursive(
            $snippets,
            $this->fetchSnippetsFromDatabase($snippetSetId, $unusedThemes)
        );
    }

    /**
     * @return array<int, string>
     */
    public function getRegionFilterItems(Context $context): array
    {
        $isoList = $this->createIsoList($context);
        $snippetFiles = $this->getSnippetFilesByIso($isoList);

        $criteria = new Criteria();
        $dbSnippets = $this->findSnippetInDatabase($criteria, $context);

        $result = [];
        foreach ($snippetFiles as $files) {
            foreach ($this->getSnippetsFromFiles($files, '') as $namespace => $_value) {
                $region = explode('.', $namespace)[0];
                if (\in_array($region, $result, true)) {
                    continue;
                }

                $result[] = $region;
            }
        }

        foreach ($dbSnippets as $snippet) {
            $region = explode('.', $snippet->getTranslationKey())[0];
            if (\in_array($region, $result, true)) {
                continue;
            }

            $result[] = $region;
        }
        sort($result);

        return $result;
    }

    /**
     * @return array<int, int|string>
     */
    public function getAuthors(Context $context): array
    {
        $files = $this->snippetFileCollection->toArray();

        $criteria = new Criteria();
        $criteria->addAggregation(new TermsAggregation('distinct_author', 'author'));

        $aggregation = $this->snippetRepository->aggregate($criteria, $context)->get('distinct_author');

        if (!$aggregation instanceof TermsResult || empty($aggregation->getBuckets())) {
            $result = [];
        } else {
            $result = $aggregation->getKeys();
        }

        $authors = array_flip($result);
        foreach ($files as $file) {
            $authors[$file['author']] = true;
        }
        $result = array_keys($authors);
        sort($result);

        return $result;
    }

    public function findSnippetSetId(string $salesChannelId, string $languageId, string $locale): string
    {
        $snippetSetId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`snippet_set`.`id`))
            FROM `sales_channel_domain`
            INNER JOIN `snippet_set` ON `sales_channel_domain`.`snippet_set_id` = `snippet_set`.`id`
            WHERE `sales_channel_domain`.`sales_channel_id` = :salesChannelId AND `sales_channel_domain`.`language_id` = :languageId
            LIMIT 1',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        if ($snippetSetId) {
            return $snippetSetId;
        }

        $sets = $this->connection->fetchAllKeyValue(
            'SELECT iso, LOWER(HEX(id)) FROM snippet_set WHERE iso IN (:locales) LIMIT 2',
            ['locales' => array_unique([$locale, 'en-GB'])],
            ['locales' => ArrayParameterType::STRING]
        );

        if (isset($sets[$locale])) {
            return $sets[$locale];
        }

        return array_pop($sets);
    }

    /**
     * @param list<string> $usingThemes
     *
     * @return list<string>
     */
    protected function getUnusedThemes(array $usingThemes = []): array
    {
        if (!$this->container->has(StorefrontPluginRegistry::class)) {
            return [];
        }

        $unusedThemes = $this->container->get(StorefrontPluginRegistry::class)->getConfigurations()->getThemes()
            ->filter(fn (StorefrontPluginConfiguration $theme) => !\in_array($theme->getTechnicalName(), $usingThemes, true))
            ->map(fn (StorefrontPluginConfiguration $theme) => $theme->getTechnicalName());

        return array_values($unusedThemes);
    }

    /**
     * Second parameter $unusedThemes is used for external dependencies
     *
     * @param list<string> $unusedThemes
     *
     * @return array<string, string>
     */
    protected function fetchSnippetsFromDatabase(string $snippetSetId, array $unusedThemes = []): array
    {
        /** @var array<string, string> $snippets */
        $snippets = $this->connection->fetchAllKeyValue('SELECT translation_key, value FROM snippet WHERE snippet_set_id = :snippetSetId', [
            'snippetSetId' => Uuid::fromHexToBytes($snippetSetId),
        ]);

        return $snippets;
    }

    /**
     * @return array<string, string>
     */
    private function getSnippetsByLocale(SnippetFileCollection $snippetFileCollection, string $locale): array
    {
        $files = $snippetFileCollection->getSnippetFilesByIso($locale);
        $snippets = [];

        foreach ($files as $file) {
            $json = $this->decodeSnippetFileJson($file);

            $flattenSnippetFileSnippets = $this->flatten($json);

            $snippets = array_replace_recursive($snippets, $flattenSnippetFileSnippets);
        }

        return $snippets;
    }

    /**
     * @return list<string>
     */
    private function getUsedThemes(?string $salesChannelId = null): array
    {
        if (!$this->container->has(StorefrontPluginRegistry::class)) {
            return [];
        }

        if (!$salesChannelId || $this->salesChannelThemeLoader === null) {
            return [StorefrontPluginRegistry::BASE_THEME_NAME];
        }

        $usedThemes = $this->salesChannelThemeLoader->load($salesChannelId);

        return array_values(array_unique([
            ...$usedThemes,
            StorefrontPluginRegistry::BASE_THEME_NAME, // Storefront snippets should always be loaded
        ]));
    }

    /**
     * @param array<string, string> $isoList
     *
     * @return array<string, list<AbstractSnippetFile>>
     */
    private function getSnippetFilesByIso(array $isoList): array
    {
        $result = [];
        foreach ($isoList as $iso) {
            $result[$iso] = $this->snippetFileCollection->getSnippetFilesByIso($iso);
        }

        return $result;
    }

    /**
     * @param array<int, AbstractSnippetFile> $languageFiles
     *
     * @return array<string, Snippet>
     */
    private function getSnippetsFromFiles(array $languageFiles, string $setId): array
    {
        $result = [];
        foreach ($languageFiles as $snippetFile) {
            $json = $this->decodeSnippetFileJson($snippetFile);

            $flattenSnippetFileSnippets = $this->flatten(
                $json,
                additionalParameters: ['author' => $snippetFile->getAuthor(), 'id' => null, 'setId' => $setId]
            );

            $result = array_replace_recursive($result, $flattenSnippetFileSnippets);
        }

        return $result;
    }

    /**
     * @param SnippetArray $sets
     *
     * @return array<string, list<Snippet>>
     */
    private function mergeSnippetsComparison(array $sets): array
    {
        $result = [];
        foreach ($sets as $snippetSet) {
            foreach ($snippetSet['snippets'] as $translationKey => $snippet) {
                $result[$translationKey][] = $snippet;
            }
        }

        return $result;
    }

    private function getLocaleBySnippetSetId(string $snippetSetId): string
    {
        $locale = $this->connection->fetchOne('SELECT iso FROM snippet_set WHERE id = :snippetSetId', [
            'snippetSetId' => Uuid::fromHexToBytes($snippetSetId),
        ]);

        if ($locale === false) {
            throw SnippetException::snippetSetNotFound($snippetSetId);
        }

        return (string) $locale;
    }

    /**
     * @param SnippetArray $fileSnippets
     * @param array<string, string> $isoList
     *
     * @return SnippetArray
     */
    private function fillBlankSnippets(array $fileSnippets, array $isoList): array
    {
        foreach ($isoList as $setId => $_iso) {
            foreach ($isoList as $currentSetId => $_currentIso) {
                if ($setId === $currentSetId) {
                    continue;
                }

                foreach ($fileSnippets[$setId]['snippets'] as $index => $_snippet) {
                    if (!isset($fileSnippets[$currentSetId]['snippets'][$index])) {
                        $fileSnippets[$currentSetId]['snippets'][$index] = [
                            'value' => '',
                            'translationKey' => $index,
                            'author' => '',
                            'origin' => '',
                            'resetTo' => '',
                            'setId' => $currentSetId,
                            'id' => null,
                        ];
                    }
                }

                ksort($fileSnippets[$currentSetId]['snippets']);
            }
        }

        return $fileSnippets;
    }

    /**
     * @param array<string, list<AbstractSnippetFile>> $languageFiles
     * @param array<string, string> $isoList
     *
     * @return SnippetArray
     */
    private function getFileSnippets(array $languageFiles, array $isoList): array
    {
        $fileSnippets = [];

        foreach ($isoList as $setId => $iso) {
            $fileSnippets[$setId]['snippets'] = $this->getSnippetsFromFiles($languageFiles[$iso], $setId);
        }

        return $fileSnippets;
    }

    /**
     * @return array<string, string>
     */
    private function createIsoList(Context $context): array
    {
        $isoList = [];

        foreach ($this->getSetMetaData($context) as $set) {
            $isoList[$set['id']] = $set['iso'];
        }

        return $isoList;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSetMetaData(Context $context): array
    {
        $snippetSets = $this->findSnippetSetInDatabase(new Criteria(), $context);
        $result = [];
        foreach ($snippetSets as $key => $value) {
            $result[(string) $key] = $value->jsonSerialize();
        }

        return $result;
    }

    /**
     * @param SnippetArray $fileSnippets
     *
     * @return SnippetArray
     */
    private function databaseSnippetsToArray(SnippetCollection $snippets, array $fileSnippets): array
    {
        $result = [];
        foreach ($snippets as $snippet) {
            /** @var array{value: string, translationKey: string, setId: string, id: string, author: string} $snippetArray */
            $snippetArray = $snippet->jsonSerialize();
            $currentSnippet = array_intersect_key(
                $snippetArray,
                array_flip([
                    'author',
                    'id',
                    'setId',
                    'translationKey',
                    'value',
                ])
            );

            $currentSnippet['origin'] = '';
            $currentSnippet['resetTo'] = $fileSnippets[$snippet->getSetId()]['snippets'][$snippet->getTranslationKey()]['origin'] ?? $snippet->getValue();
            $result[$snippet->getSetId()]['snippets'][$snippet->getTranslationKey()] = $currentSnippet;
        }

        return $result;
    }

    private function findSnippetInDatabase(Criteria $criteria, Context $context): SnippetCollection
    {
        return $this->snippetRepository->search($criteria, $context)->getEntities();
    }

    private function findSnippetSetInDatabase(Criteria $criteria, Context $context): SnippetSetCollection
    {
        return $this->snippetSetRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param SnippetSort $sort
     * @param SnippetArray $snippets
     *
     * @return SnippetArray
     */
    private function sortSnippets(array $sort, array $snippets): array
    {
        if (!isset($sort['sortBy'], $sort['sortDirection'])) {
            return $snippets;
        }

        if ($sort['sortBy'] === 'translationKey' || $sort['sortBy'] === 'id') {
            foreach ($snippets as &$set) {
                if ($sort['sortDirection'] === 'ASC') {
                    ksort($set['snippets']);
                } elseif ($sort['sortDirection'] === 'DESC') {
                    krsort($set['snippets']);
                }
            }

            return $snippets;
        }

        if (!isset($snippets[$sort['sortBy']])) {
            return $snippets;
        }

        $mainSet = $snippets[$sort['sortBy']];
        unset($snippets[$sort['sortBy']]);

        uasort($mainSet['snippets'], static function ($a, $b) use ($sort) {
            $a = mb_strtolower((string) $a['value']);
            $b = mb_strtolower((string) $b['value']);

            return $sort['sortDirection'] !== 'DESC' ? $a <=> $b : $b <=> $a;
        });

        $result = [$sort['sortBy'] => $mainSet];
        foreach ($snippets as $setId => $set) {
            foreach ($mainSet['snippets'] as $currentKey => $_value) {
                $result[$setId]['snippets'][$currentKey] = $set['snippets'][$currentKey];
            }
        }

        return $result;
    }

    /**
     * @param array<string, string|array<string, mixed>> $array
     * @param array<string, string|null>|null $additionalParameters
     *
     * @return array<string, string|array<string, mixed>>
     */
    private function flatten(array $array, string $prefix = '', ?array $additionalParameters = null): array
    {
        $result = [];
        foreach ($array as $index => $value) {
            $newIndex = $prefix . (empty($prefix) ? '' : '.') . $index;

            if (\is_array($value)) {
                $result = [...$result, ...$this->flatten($value, $newIndex, $additionalParameters)];
            } else {
                if (!empty($additionalParameters)) {
                    $result[$newIndex] = array_merge([
                        'value' => $value,
                        'origin' => $value,
                        'resetTo' => $value,
                        'translationKey' => $newIndex,
                    ], $additionalParameters);

                    continue;
                }

                $result[$newIndex] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string|array<string, mixed>>
     */
    private function decodeSnippetFileJson(AbstractSnippetFile $snippetFile): array
    {
        try {
            $json = json_decode((string) file_get_contents($snippetFile->getPath()), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw SnippetException::invalidSnippetFile($snippetFile->getPath(), $e);
        }

        return $json;
    }
}
