<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Services;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetEntity;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
use Shopware\Core\Framework\Snippet\SnippetEntity;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetService implements SnippetServiceInterface
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
     * @var SnippetFlattenerInterface
     */
    private $snippetFlattener;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $snippetSetRepository;

    public function __construct(
        Connection $connection,
        SnippetFlattenerInterface $snippetFlattener,
        SnippetFileCollection $snippetFileCollection,
        EntityRepositoryInterface $snippetRepository,
        EntityRepositoryInterface $snippetSetRepository
    ) {
        $this->connection = $connection;
        $this->snippetFileCollection = $snippetFileCollection;
        $this->snippetFlattener = $snippetFlattener;
        $this->snippetRepository = $snippetRepository;
        $this->snippetSetRepository = $snippetSetRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(int $page, int $limit, Context $context, array $filters): array
    {
        --$page;
        $metaData = $this->getSetMetaData($context);
        $isoList = $this->createIsoList($metaData);
        $languageFiles = $this->getSnippetFilesByIso($isoList);

        $snippets = [];
        $fileSnippets = $this->getFileSnippets($languageFiles);

        if (count($filters['namespaces']) === 0 && count($filters['authors']) === 0) {
            $snippets = array_merge_recursive($fileSnippets, []);
        }

        $snippets = $this->applyTranslationKeyFilter($filters['translationKeys'], $snippets, $isoList, $context);
        $snippets = $this->applyNamespaceFilter($filters['namespaces'], $snippets, $fileSnippets);
        $snippets = $this->applyCustomSnippets($context, $snippets, $languageFiles, $metaData, $filters['isCustom']);
        $snippets = $this->applyTermFilter($isoList, $context, $filters['term'], $snippets, $filters['isCustom']);
        $snippets = $this->fillBlankSnippets($isoList, $snippets);
        $snippets = $this->applyEmptySnippetsFilter($filters['emptySnippets'], $isoList, $snippets, $fileSnippets);

        $total = 0;
        $sets = [];
        $translationKeyList = [];
        foreach (array_keys($metaData) as $snippetSetId) {
            $iso = $metaData[$snippetSetId]['iso'];
            $set = $metaData[$snippetSetId];

            $currentfileSnippets = $snippets[$iso]['snippets'];
            $currentfileSnippets = array_chunk($currentfileSnippets, $limit, true);
            $total = max(count($snippets[$iso]['snippets']), $total);

            $currentPage = $currentfileSnippets[$page] ?? [];
            $set['snippets'] = $currentPage;

            $translationKeyList = array_keys($currentPage);
            $sets[] = $set;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('snippet.translationKey', $translationKeyList));
        $queryResult = $this->findSnippetInDatabase($criteria, $context);

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
            $currentSnippet['resetTo'] = $snippet->getValue();
            $result[$snippet->getSetId()][] = $currentSnippet;
        }

        foreach ($sets as &$set) {
            $setSnippets = $result[$set['id']] ?? [];
            $set['snippets'] = $this->mergeSnippets($set['snippets'], $setSnippets, $set['id']);
        }
        unset($set);

        return [
            'total' => $total,
            'data' => $this->mergeSnippetsComparison($sets),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getStorefrontSnippets(MessageCatalogueInterface $catalog, string $snippetSetId): array
    {
        $locale = $this->getLocaleBySnippetSetId($snippetSetId);
        $languageFiles = $this->snippetFileCollection->getSnippetFilesByIso($locale);
        $fileSnippets = $catalog->all('messages');

        /** @var SnippetFileInterface $snippetFile */
        foreach ($languageFiles as $key => $snippetFile) {
            $flattenSnippetFileSnippets = $this->snippetFlattener->flatten(
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

    /**
     * {@inheritdoc}
     */
    public function getRegionFilterItems(Context $context): array
    {
        $metaData = $this->getSetMetaData($context);
        $isoList = $this->createIsoList($metaData);
        $languageFiles = $this->getSnippetFilesByIso($isoList);

        $result = [];
        foreach ($languageFiles as $isoFiles) {
            $snippets = $this->getSnippetsFromFiles($isoFiles);
            foreach ($snippets as $namespace => $value) {
                $region = explode('.', $namespace)[0];
                if (in_array($region, $result)) {
                    continue;
                }

                $result[] = $region;
            }
        }

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

    private function applyTranslationKeyFilter(array $translationKeys, array $snippets, array $isoList, Context $context): array
    {
        if (count($translationKeys) <= 0) {
            return $snippets;
        }

        $result = $this->createEmptyEmptySnippetsResult($isoList);
        foreach ($isoList as $iso) {
            foreach ($snippets[$iso]['snippets'] as $key => $value) {
                if (!in_array($key, $translationKeys, true)) {
                    continue;
                }

                $result[$iso]['snippets'][$key] = $value;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('snippet.translationKey', $translationKeys));
        $dbSnippets = $this->findSnippetInDatabase($criteria, $context);

        foreach ($dbSnippets as $snippet) {
            $result[$isoList[$snippet->getSetId()]]['snippets'][$snippet->getTranslationKey()] = $snippet->getValue();
        }

        return $result;
    }

    private function applyEmptySnippetsFilter($emptySnippets, $isoList, $result, $fileSnippets): array
    {
        if (!$emptySnippets) {
            return $result;
        }

        $emptySnippetsResult = $this->createEmptyEmptySnippetsResult($isoList);
        foreach ($result as $currentIso => $tmpSnippets) {
            foreach ($isoList as $iso) {
                if ($currentIso === $iso) {
                    continue;
                }

                foreach ($tmpSnippets['snippets'] as $key => $value) {
                    if ((!isset($result[$iso]['snippets'][$key]) && !isset($fileSnippets[$iso]['snippets'][$key])) || $result[$iso]['snippets'][$key] === '') {
                        $emptySnippetsResult[$iso]['snippets'][$key] = '';
                        $emptySnippetsResult[$currentIso]['snippets'][$key] = $result[$currentIso]['snippets'][$key];
                    }
                }
            }
        }

        return $emptySnippetsResult;
    }

    private function applyNamespaceFilter(array $namespaces, array $result, array $fileSnippets): array
    {
        if (count($namespaces) <= 0) {
            return $result;
        }

        foreach ($namespaces as $term) {
            $result = array_merge_recursive(
                $result,
                $this->findSnippetsInFiles(sprintf('%s*', $term), $fileSnippets)
            );
        }

        return $result;
    }

    private function applyTermFilter(array $isoList, Context $context, ?string $term, array $result, ?bool $customSnippets): array
    {
        if (!$term) {
            return $result;
        }

        if ($customSnippets) {
            $result = $this->createEmptyEmptySnippetsResult($isoList);
        } else {
            $result = $this->findSnippetsInFiles(sprintf('*%s*', $term), $result);
        }

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.value', $term), 1));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.translationKey', $term), 1));

        $dbSnippets = $this->findSnippetInDatabase($criteria, $context);

        /** @var SnippetEntity $snippet */
        foreach ($dbSnippets as $snippet) {
            if (!isset($isoList[$snippet->getSetId()])) {
                continue;
            }

            $result[$isoList[$snippet->getSetId()]]['snippets'][$snippet->getTranslationKey()] = $snippet->getValue();
        }

        return $result;
    }

    private function applyCustomSnippets(Context $context, array $snippets, array $languageFiles, array $metaData, ?bool $customSnippets): array
    {
        if (!$customSnippets) {
            return $snippets;
        }

        $authors = [];
        $snippets = [];
        $isoList = [];
        foreach ($languageFiles as $isoFiles) {
            /** @var SnippetFileInterface $file */
            foreach ($isoFiles as $file) {
                $isoList[] = $file->getIso();
                if (in_array($file->getAuthor(), $authors)) {
                    continue;
                }

                $authors[] = $file->getAuthor();
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('snippet.author', $authors)]));
        $tmp = $this->findSnippetInDatabase($criteria, $context);

        /* @var SnippetEntity $databaseSnippet */
        foreach ($metaData as $set) {
            foreach ($tmp as $databaseSnippet) {
                if ($set['id'] !== $databaseSnippet->getSetId()) {
                    continue;
                }

                $snippets[$set['iso']]['snippets'][$databaseSnippet->getTranslationKey()] = $databaseSnippet->getValue();
            }
        }

        if (count($snippets) === 0) {
            $snippets = $this->createEmptyEmptySnippetsResult($isoList);
        }

        return $this->fillBlankSnippets($isoList, $snippets);
    }

    private function getDefaultLocale(): string
    {
        $locale = $this->connection->createQueryBuilder()
            ->select(['code'])
            ->from('locale')
            ->where('id = :localeId')
            ->setParameter('localeId', Uuid::fromHexToBytes(Defaults::LOCALE_SYSTEM))
            ->execute()
            ->fetchColumn();

        return $locale ?: Defaults::LOCALE_EN_GB_ISO;
    }

    private function getSnippetFilesByIso(array $isoList): array
    {
        $result = [];
        foreach ($isoList as $iso) {
            $result[$iso] = $this->snippetFileCollection->getSnippetFilesByIso($iso);
        }

        return $result;
    }

    private function getSnippetsFromFiles(array $languageFiles): array
    {
        $result = [];
        /** @var SnippetFileInterface $snippetFile */
        foreach ($languageFiles as $key => $snippetFile) {
            $flattenSnippetFileSnippets = $this->snippetFlattener->flatten(
                json_decode(file_get_contents($snippetFile->getPath()), true) ?: []
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

    private function mergeSnippets(array $fileSnippets, array $dbSnippets, string $snippetSetId): array
    {
        $snippets = [];
        foreach ($fileSnippets as $translationKey => $value) {
            $snippets[$translationKey] = [
                'id' => null,
                'value' => $value,
                'resetTo' => $value,
                // Todo: @m.brode and @d.garding fix origin - Ticket NEXT-1667
                'origin' => $value,
                'translationKey' => $translationKey,
                'setId' => $snippetSetId,
                'author' => Defaults::SNIPPET_AUTHOR,
            ];
        }

        foreach ($dbSnippets as $dbSnippet) {
            if (!isset($snippets[$dbSnippet['translationKey']])) {
                continue;
            }

            $dbSnippet['origin'] = $fileSnippets[$dbSnippet['translationKey']];
            $dbSnippet['resetTo'] = $dbSnippet['value'];
            $snippets[$dbSnippet['translationKey']] = $dbSnippet;
        }

        return $snippets;
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
            $locale = $this->getDefaultLocale();
        }

        return $locale;
    }

    private function createEmptyEmptySnippetsResult(array $isoList): array
    {
        $result = [];
        foreach ($isoList as $iso) {
            $result[$iso]['snippets'] = [];
        }

        return $result;
    }

    private function fillBlankSnippets(array $isoList, array $fileSnippets): array
    {
        foreach ($isoList as $iso) {
            foreach ($isoList as $currentIso) {
                if ($iso === $currentIso) {
                    continue;
                }

                foreach ($fileSnippets[$iso]['snippets'] as $index => $snippet) {
                    if (!isset($fileSnippets[$currentIso]['snippets'][$index])) {
                        $fileSnippets[$currentIso]['snippets'][$index] = '';
                    }
                }

                ksort($fileSnippets[$currentIso]['snippets']);
            }
        }

        return $fileSnippets;
    }

    private function findSnippetsInFiles(string $term, array $fileSnippets): array
    {
        $result = [];
        if ($term) {
            foreach ($fileSnippets as $iso => $snippets) {
                $result[$iso]['snippets'] = array_filter($snippets['snippets'], function ($arrayValue, $arrayIndex) use ($term) {
                    if (fnmatch($term, $arrayValue, FNM_CASEFOLD) || fnmatch($term, $arrayIndex, FNM_CASEFOLD)) {
                        return true;
                    }

                    return false;
                }, ARRAY_FILTER_USE_BOTH);
            }
        }

        return $result;
    }

    private function getFileSnippets(array $languageFiles): array
    {
        $fileSnippets = [];
        foreach ($languageFiles as $iso => $isoLanguageFiles) {
            $fileSnippets[$iso]['snippets'] = $this->getSnippetsFromFiles($isoLanguageFiles);
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

    private function findSnippetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetRepository->search($criteria, $context)->getEntities()->getElements();
    }

    private function findSnippetSetInDatabase(Criteria $criteria, Context $context): array
    {
        return $this->snippetSetRepository->search($criteria, $context)->getEntities()->getElements();
    }
}
