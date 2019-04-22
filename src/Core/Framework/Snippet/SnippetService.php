<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetService
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SnippetFileCollection
     */
    private $snippetFileCollection;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetSetRepository;

    /**
     * @var SnippetFilterFactory
     */
    private $snippetFilterFactory;

    public function __construct(
        Connection $connection,
        SnippetFileCollection $snippetFileCollection,
        EntityRepositoryInterface $snippetRepository,
        EntityRepositoryInterface $snippetSetRepository,
        SnippetFilterFactory $snippetFilterFactory
    ) {
        $this->connection = $connection;
        $this->snippetFileCollection = $snippetFileCollection;
        $this->snippetRepository = $snippetRepository;
        $this->snippetSetRepository = $snippetSetRepository;
        $this->snippetFilterFactory = $snippetFilterFactory;
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
     */
    public function getList(int $page, int $limit, Context $context, array $requestFilters, array $sort): array
    {
        --$page;
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
        foreach ($snippets as $setId => &$set) {
            $total = $total > 0 ? $total : count($set['snippets']);
            $set['snippets'] = array_chunk($set['snippets'], $limit, true)[$page] ?? [];
        }

        return [
            'total' => $total,
            'data' => $this->mergeSnippetsComparison($snippets),
        ];
    }

    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId): array
    {
        $locale = $this->getLocaleBySnippetSetId($snippetSetId);
        $languageFiles = $this->snippetFileCollection->getSnippetFilesByIso($locale);
        $fileSnippets = $catalog->all('messages');

        /** @var SnippetFileInterface $snippetFile */
        foreach ($languageFiles as $key => $snippetFile) {
            $flattenSnippetFileSnippets = $this->flatten(
                json_decode(file_get_contents($snippetFile->getPath()), true) ?: []
            );

            $fileSnippets = array_replace_recursive(
                $fileSnippets,
                $flattenSnippetFileSnippets
            );
        }

        $snippets = array_replace_recursive(
            $fileSnippets,
            $this->fetchSnippetsFromDatabase($snippetSetId)
        );

        return $snippets;
    }

    public function getRegionFilterItems(Context $context): array
    {
        $metaData = $this->getSetMetaData($context);
        $isoList = $this->createIsoList($metaData);
        $snippetFiles = $this->getSnippetFilesByIso($isoList);

        $criteria = new Criteria();
        $dbSnippets = $this->findSnippetInDatabase($criteria, $context);

        $result = [];
        foreach ($snippetFiles as $files) {
            foreach ($this->getSnippetsFromFiles($files, '') as $namespace => $value) {
                $region = explode('.', $namespace)[0];
                if (in_array($region, $result, true)) {
                    continue;
                }

                $result[] = $region;
            }
        }

        foreach ($dbSnippets as $snippet) {
            $region = explode('.', $snippet->getTranslationKey())[0];
            if (in_array($region, $result, true)) {
                continue;
            }

            $result[] = $region;
        }
        sort($result);

        return $result;
    }

    public function getAuthors(Context $context): array
    {
        $files = $this->snippetFileCollection->toArray();

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueAggregation('author', 'distinct_author'));
        $result = $this->snippetRepository->aggregate($criteria, $context)->getAggregations()->get('distinct_author')->getResultByKey(null)['values'] ?? [];

        $authors = array_flip($result);
        foreach ($files as $file) {
            $authors[$file['author']] = true;
        }
        $result = array_keys($authors);
        sort($result);

        return $result;
    }

    private function fetchSnippetsFromDatabase(string $snippetSetId): array
    {
        $snippets = $this->connection->createQueryBuilder()
            ->select(['snippet.translation_key', 'snippet.value'])
            ->from('snippet')
            ->where('snippet.snippet_set_id = :snippetSetId')
            ->setParameter('snippetSetId', Uuid::fromHexToBytes($snippetSetId))
            ->addGroupBy('snippet.translation_key')
            ->addGroupBy('snippet.id')
            ->execute()
            ->fetchAll();

        return FetchModeHelper::keyPair($snippets);
    }

    private function getSnippetFilesByIso(array $isoList): array
    {
        $result = [];
        foreach ($isoList as $iso) {
            $result[$iso] = $this->snippetFileCollection->getSnippetFilesByIso($iso);
        }

        return $result;
    }

    private function getSnippetsFromFiles(array $languageFiles, string $setId): array
    {
        $result = [];
        /** @var SnippetFileInterface $snippetFile */
        foreach ($languageFiles as $key => $snippetFile) {
            $flattenSnippetFileSnippets = $this->flatten(
                json_decode(file_get_contents($snippetFile->getPath()), true) ?: [],
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
        $locale = $this->connection->createQueryBuilder()
            ->select(['iso'])
            ->from('snippet_set')
            ->where('id = :snippetSetId')
            ->setParameter('snippetSetId', Uuid::fromHexToBytes($snippetSetId))
            ->execute()
            ->fetchColumn();

        if ($locale === false) {
            throw new \InvalidArgumentException(sprintf('No snippetSet with id "%s" found', $snippetSetId));
        }

        return $locale;
    }

    private function fillBlankSnippets(array $fileSnippets, array $isoList): array
    {
        foreach ($isoList as $setId => $iso) {
            foreach ($isoList as $currentSetId => $currentIso) {
                if ($setId === $currentSetId) {
                    continue;
                }

                foreach ($fileSnippets[$setId]['snippets'] as $index => $snippet) {
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

    private function getFileSnippets(array $languageFiles, array $isoList): array
    {
        $fileSnippets = [];

        foreach ($isoList as $setId => $iso) {
            $fileSnippets[$setId]['snippets'] = $this->getSnippetsFromFiles($languageFiles[$iso], $setId);
        }

        return $fileSnippets;
    }

    private function createIsoList(array $metaData): array
    {
        $isoList = [];
        foreach ($metaData as $set) {
            $isoList[$set['id']] = $set['iso'];
        }

        return $isoList;
    }

    private function getSetMetaData(Context $context): array
    {
        $queryResult = $this->findSnippetSetInDatabase(new Criteria(), $context);

        $result = [];
        /** @var SnippetSetEntity $value */
        foreach ($queryResult as $key => $value) {
            $result[$key] = $value->toArray();
        }

        return $result;
    }

    private function databaseSnippetsToArray(array $queryResult, array $fileSnippets): array
    {
        $result = [];
        foreach ($queryResult as $snippet) {
            $currentSnippet = array_intersect_key(
                $snippet->toArray(),
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

    private function findSnippetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetRepository->search($criteria, $context)->getEntities()->getElements();
    }

    private function findSnippetSetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetSetRepository->search($criteria, $context)->getEntities()->getElements();
    }

    private function sortSnippets(array $sort, array $snippets): array
    {
        if (!isset($sort['sortBy'], $sort['sortDirection'])) {
            return $snippets;
        }

        if ($sort['sortBy'] === 'translationKey' || $sort['sortBy'] === 'id') {
            foreach ($snippets as $setId => &$set) {
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

        uasort($mainSet['snippets'], function ($a, $b) use ($sort) {
            $a = strtolower($a['value']);
            $b = strtolower($b['value']);

            return $sort['sortDirection'] !== 'DESC' ? $a > $b : $a <= $b;
        });

        $result = [$sort['sortBy'] => $mainSet];
        foreach ($snippets as $setId => $set) {
            foreach ($mainSet['snippets'] as $currentKey => $value) {
                $result[$setId]['snippets'][$currentKey] = $set['snippets'][$currentKey];
            }
        }

        return $result;
    }

    private function flatten(array $array, string $prefix = '', ?array $additionalParameters = null): array
    {
        $result = [];
        foreach ($array as $index => $value) {
            $newIndex = $prefix . (empty($prefix) ? '' : '.') . $index;

            if (\is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $newIndex, $additionalParameters));
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
