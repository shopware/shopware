<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Storefront\Theme\SalesChannelThemeLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

#[Package('system-settings')]
class SnippetService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SnippetFileCollection $snippetFileCollection,
        private readonly EntityRepository $snippetRepository,
        private readonly EntityRepository $snippetSetRepository,
        private readonly EntityRepository $salesChannelDomain,
        private readonly SnippetFilterFactory $snippetFilterFactory,
        /**
         * The "kernel" service is synthetic, it needs to be set at boot time before it can be used.
         * We need to get StorefrontPluginRegistry service from service_container lazily because it depends on kernel service.
         */
        private readonly ContainerInterface $container,
        private readonly ?SalesChannelThemeLoader $salesChannelThemeLoader = null
    ) {
    }

    /**
     * filters: [
     *      'isCustom' => bool,
     *      'isEmpty' => bool,
     *      'term' => string,
     *      'namespaces' => array,
     *      'authors' => array,
     *      'translationKeys' => array,
     * ]
     *
     * sort: [
     *      'column' => NULL || the string -> 'translationKey' || setId
     *      'direction' => 'ASC' || 'DESC'
     * ]
     *
     * @param int<1, max> $limit
     * @param array<string, bool|string|array<int, string>> $requestFilters
     * @param array<string, string> $sort
     *
     * @return array{total:int, data: array<string, array<int, array<string, string|null>>>}
     */
    public function getList(int $page, int $limit, Context $context, array $requestFilters, array $sort): array
    {
        --$page;
        /** @var array<string, array{iso: string, id: string}> $metaData */
        $metaData = $this->getSetMetaData($context);

        $isoList = $this->createIsoList($metaData);
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

        $snippetFileCollection = clone $this->snippetFileCollection;

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
        /** @var array<string, array{iso: string, id: string}> $metaData */
        $metaData = $this->getSetMetaData($context);
        $isoList = $this->createIsoList($metaData);
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

        /** @var SnippetEntity $snippet */
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

        /** @var TermsResult|null $aggregation */
        $aggregation = $this->snippetRepository->aggregate($criteria, $context)
                ->get('distinct_author');

        if (!$aggregation || empty($aggregation->getBuckets())) {
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

    public function getSnippetSet(string $salesChannelId, string $languageId, string $locale, Context $context): ?SnippetSetEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('languageId', $languageId)
        );
        $criteria->addAssociation('snippetSet');

        /** @var SalesChannelDomainEntity|null $salesChannelDomain */
        $salesChannelDomain = $this->salesChannelDomain->search($criteria, $context)->first();

        if ($salesChannelDomain === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $locale));
            $snippetSet = $this->snippetSetRepository->search($criteria, $context)->first();
        } else {
            $snippetSet = $salesChannelDomain->getSnippetSet();
        }

        return $snippetSet;
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

        $themeRegistry = $this->container->get(StorefrontPluginRegistry::class);

        $unusedThemes = $themeRegistry->getConfigurations()->getThemes()->filter(fn (StorefrontPluginConfiguration $theme) => !\in_array($theme->getTechnicalName(), $usingThemes, true))->map(fn (StorefrontPluginConfiguration $theme) => $theme->getTechnicalName());

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
            $json = json_decode(file_get_contents($file->getPath()) ?: '', true);

            $jsonError = json_last_error();
            if ($jsonError !== 0) {
                throw new \RuntimeException(sprintf('Invalid JSON in snippet file at path \'%s\' with code \'%d\'', $file->getPath(), $jsonError));
            }

            $flattenSnippetFileSnippets = $this->flatten($json);

            $snippets = array_replace_recursive(
                $snippets,
                $flattenSnippetFileSnippets
            );
        }

        return $snippets;
    }

    /**
     * @return list<string>
     */
    private function getUsedThemes(?string $salesChannelId = null): array
    {
        if (!$salesChannelId || $this->salesChannelThemeLoader === null) {
            return [StorefrontPluginRegistry::BASE_THEME_NAME];
        }

        $saleChannelThemes = $this->salesChannelThemeLoader->load($salesChannelId);

        $usedThemes = array_filter([
            $saleChannelThemes['themeName'] ?? null,
            $saleChannelThemes['parentThemeName'] ?? null,
        ]);

        /** @var list<string> */
        return array_values(array_unique([
            ...$usedThemes,
            StorefrontPluginRegistry::BASE_THEME_NAME, // Storefront snippets should always be loaded
        ]));
    }

    /**
     * @param array<string, string> $isoList
     *
     * @return array<string, array<int, AbstractSnippetFile>>
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
     * @return array<string, array<string, string|null>>
     */
    private function getSnippetsFromFiles(array $languageFiles, string $setId): array
    {
        $result = [];
        foreach ($languageFiles as $snippetFile) {
            $json = json_decode((string) file_get_contents($snippetFile->getPath()), true);

            $jsonError = json_last_error();
            if ($jsonError !== 0) {
                throw new \RuntimeException(sprintf('Invalid JSON in snippet file at path \'%s\' with code \'%d\'', $snippetFile->getPath(), $jsonError));
            }

            $flattenSnippetFileSnippets = $this->flatten(
                $json,
                '',
                ['author' => $snippetFile->getAuthor(), 'id' => null, 'setId' => $setId]
            );

            $result = array_replace_recursive(
                $result,
                $flattenSnippetFileSnippets
            );
        }

        return $result;
    }

    /**
     * @param array<string, array<string, array<string, array<string, string|null>>>> $sets
     *
     * @return array<string, array<int, array<string, string|null>>>
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
            throw new \InvalidArgumentException(sprintf('No snippetSet with id "%s" found', $snippetSetId));
        }

        return (string) $locale;
    }

    /**
     * @param array<string, array<string, array<string, array<string, string|null>>>> $fileSnippets
     * @param array<string, string> $isoList
     *
     * @return array<string, array<string, array<string, array<string, string|null>>>>
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
     * @param array<string, array<int, AbstractSnippetFile>> $languageFiles
     * @param array<string, string> $isoList
     *
     * @return array<string, array<string, array<string, array<string, string|null>>>>
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
     * @param array<string, array{iso: string, id: string}> $metaData
     *
     * @return array<string, string>
     */
    private function createIsoList(array $metaData): array
    {
        $isoList = [];

        foreach ($metaData as $set) {
            $isoList[$set['id']] = $set['iso'];
        }

        return $isoList;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function getSetMetaData(Context $context): array
    {
        $queryResult = $this->findSnippetSetInDatabase(new Criteria(), $context);

        /** @var array<string, array{iso: string, id: string}> $result */
        $result = [];
        /** @var SnippetSetEntity $value */
        foreach ($queryResult as $key => $value) {
            $result[$key] = $value->jsonSerialize();
        }

        return $result;
    }

    /**
     * @param array<string, Entity> $queryResult
     * @param array<string, array<string, array<string, array<string, string|null>>>> $fileSnippets
     *
     * @return array<string, array<string, array<string, array<string, string|null>>>>
     */
    private function databaseSnippetsToArray(array $queryResult, array $fileSnippets): array
    {
        $result = [];
        /** @var SnippetEntity $snippet */
        foreach ($queryResult as $snippet) {
            $currentSnippet = array_intersect_key(
                $snippet->jsonSerialize(),
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

    /**
     * @return array<string, Entity>
     */
    private function findSnippetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetRepository->search($criteria, $context)->getEntities()->getElements();
    }

    /**
     * @return array<string, Entity>
     */
    private function findSnippetSetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetSetRepository->search($criteria, $context)->getEntities()->getElements();
    }

    /**
     * @param array<string, string> $sort
     * @param array<string, array<string, array<string, array<string, string|null>>>> $snippets
     *
     * @return array<string, array<string, array<string, array<string, string|null>>>>
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
}
