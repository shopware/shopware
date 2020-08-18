<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

use GuzzleHttp\Client;

class WikiApiService
{
    private const INITIAL_VERSION = '6.0.0';
    private const DOC_VERSION = '1.0.0';

    /**
     * @var string
     */
    private $sbpToken;

    /**
     * @var int
     */
    private $rootCategoryId;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntityHandler
     */
    private $articleHandler;

    /**
     * @var EntityHandler
     */
    private $categoryHandler;

    public function __construct(CredentialsStruct $credentialsStruct, string $environment)
    {
        $this->sbpToken = $credentialsStruct->getToken();
        $this->rootCategoryId = $credentialsStruct->getRootCategoryId();

        $this->client = new Client(['base_uri' => $credentialsStruct->getSbpServerUrl()]);
        $idMapperClient = new Client(['base_uri' => $credentialsStruct->getIdMapperUrl()]);

        $this->articleHandler = new EntityHandler('article', $idMapperClient, $environment);
        $this->categoryHandler = new EntityHandler('category', $idMapperClient, $environment);
    }

    public function removeAllFromServer(): void
    {
        [$_globalCategoryList, $articleList] = $this->gatherCategoryChildrenAndArticles(
            $this->rootCategoryId,
            $this->getAllCategories()
        );

        echo 'Deleting ' . \count($articleList) . ' articles ...' . PHP_EOL;
        foreach ($articleList as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
            $this->articleHandler->deleteById($article);
        }

        echo 'Deleting categories...' . PHP_EOL;
        $this->deleteCategoryChildren();
    }

    public function syncFilesWithServer(DocumentTree $tree): void
    {
        echo 'Remove deleted articles and categories...' . PHP_EOL;
        $this->removeDeletedEntities($tree);

        $this->syncArticles($tree);
        $this->syncCategories($tree);
        $this->syncRoot($tree);
    }

    private function buildArticleVersionUrl(array $articleInfo): string
    {
        return vsprintf(
            '/wiki/entries/%d/localizations/%d/versions/%d',
            [
                $articleInfo['articleId'],
                $articleInfo['localeId'],
                $articleInfo['versionId'],
            ]
        );
    }

    private function getLocalizedVersionedArticle(array $articleInfo): array
    {
        $articleInLocaleWithVersionUrl = $this->buildArticleVersionUrl($articleInfo);
        $response = $this->client->get($articleInLocaleWithVersionUrl, ['headers' => $this->getBasicHeaders()]);
        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true);
    }

    private function updateArticleVersion(array $articleInfo, array $payload): void
    {
        $currentArticleVersionContents = $this->getLocalizedVersionedArticle($articleInfo);
        $articleInLocaleWithVersionUrl = $this->buildArticleVersionUrl($articleInfo);

        $versionString = self::DOC_VERSION;

        $requiredContents = [
            'id' => $articleInfo['versionId'],
            'version' => $versionString,
            'selectedVersion' => $currentArticleVersionContents,
        ];

        $articleContents = array_merge($currentArticleVersionContents, $requiredContents, $payload);

        $this->client->put(
            $articleInLocaleWithVersionUrl,
            [
                'json' => $articleContents,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    private function updateArticleLocale(array $articleInfo, array $payload): void
    {
        // create english lang
        $articleLocalUrl = vsprintf(
            '/wiki/entries/%d/localizations/%d',
            [
                $articleInfo['articleId'],
                $articleInfo['localeId'],
            ]
        );

        $response = $this->client->get(
            $articleLocalUrl,
            ['headers' => $this->getBasicHeaders()]
        );
        $responseJson = $response->getBody()->getContents();

        $articleContents = array_merge(json_decode($responseJson, true), $payload);

        $this->client->put(
            $articleLocalUrl,
            [
                'json' => $articleContents,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    private function updateArticlePriority(array $articleInfo, int $priority): void
    {
        $priorityUrl = vsprintf('/wiki/entries/%d/orderPriority/%d', [$articleInfo['articleId'], $priority]);

        $this->client->put($priorityUrl, ['headers' => $this->getBasicHeaders()]);
    }

    private function uploadArticleMedia(array $articleInfo, string $filePath): string
    {
        $mediaEndpoint = $this->buildArticleVersionUrl($articleInfo) . '/media';

        $body = fopen($filePath, 'rb');
        $response = $this->client->post(
            $mediaEndpoint,
            [
                'multipart' => [
                    [
                        'name' => $filePath,
                        'contents' => $body,
                    ],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true)[0]['fileLink'];
    }

    private function uploadCategoryMedia(int $categoryId, int $localizationId, string $filePath): string
    {
        $mediaEndpoint = vsprintf('wiki/categories/%d/localizations/%d/media', [$categoryId, $localizationId]);

        $body = fopen($filePath, 'rb');
        $response = $this->client->post(
            $mediaEndpoint,
            [
                'multipart' => [
                    [
                        'name' => $filePath,
                        'contents' => $body,
                    ],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true)[0]['fileLink'];
    }

    private function getAllCategories(): array
    {
        $response = $this->client->get(
            '/wiki/categories',
            ['headers' => $this->getBasicHeaders()]
        )->getBody()->getContents();

        return json_decode($response, true);
    }

    private function updateCategory(
        int $categoryId,
        int $parentId,
        array $oldContents,
        Document $document,
        DocumentTree $tree
    ): void {
        /** @var array $oldLocalizations */
        $oldLocalizations = $oldContents['localizations'];
        $oldContentDe = [];
        $oldContentEn = [];
        foreach ($oldLocalizations as $oldLocalization) {
            if ($oldLocalization['locale']['name'] === 'de_DE') {
                $oldContentDe = $oldLocalization;
            } elseif ($oldLocalization['locale']['name'] === 'en_GB') {
                $oldContentEn = $oldLocalization;
            }
        }

        $images = $document->getHtml()->render($tree)->getImages();
        $imageMap = [];
        if (\count($images)) {
            echo '=> Uploading ' . \count($images) . ' media file(s) ...' . PHP_EOL;
            foreach ($images as $key => $mediaFile) {
                $mediaLink = $this->uploadCategoryMedia($categoryId, $oldContentEn['id'], $mediaFile);
                $imageMap[$key] = $mediaLink;
            }
        }

        $documentMetadata = $document->getMetadata();
        $payloadGlobal = [
            'orderPriority' => $document->getPriority(),
            'active' => $documentMetadata->isActive(),
        ];

        $payloadEn = [
            'title' => $documentMetadata->getTitleEn(),
            'navigationTitle' => $documentMetadata->getTitleEn(),
            'content' => $document->getHtml()->render($tree)->getContents($imageMap),
            'searchableInAllLanguages' => true,
            'seoUrl' => $documentMetadata->getUrlEn(),
            'metaDescription' => $documentMetadata->getMetaDescriptionEn(),
        ];

        $payloadDe = [
            'title' => $documentMetadata->getTitleDe(),
            'navigationTitle' => $documentMetadata->getTitleDe(),
            'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
            'searchableInAllLanguages' => false,
            'seoUrl' => $documentMetadata->getUrlDe(),
            'metaDescription' => $documentMetadata->getMetaDescriptionDe(),
        ];

        $payloadDe = array_merge($oldContentDe, $payloadDe);
        $payloadEn = array_merge($oldContentEn, $payloadEn);

        $contents = [
            'id' => $categoryId,
            'parent' => ['id' => $parentId],
            'localizations' => [
                $payloadDe,
                $payloadEn,
            ],
        ];

        $contents = array_merge($oldContents, $contents, $payloadGlobal);

        $this->client->put(
            vsprintf('/wiki/categories/%d', [$categoryId]),
            [
                'json' => $contents,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    private function getBasicHeaders(): array
    {
        return ['X-Shopware-Token' => $this->sbpToken];
    }

    private function replaceCategoryForArticle(array $articleInfo, int $categoryId): void
    {
        $this->removeAllPreviousCategories($articleInfo['articleId']);

        $this->client->post(
            vsprintf('/wiki/categories/%s/entries', [$categoryId]),
            [
                'json' => [
                    'id' => $articleInfo['articleId'],
                    'orderPriority' => '',
                    'excludeFromSearch' => false,
                    'categories' => [],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    private function getOrCreateMissingCategoryTree(Document $document): int
    {
        $prevEntryId = $this->rootCategoryId;

        $chain = array_filter($document->createParentChain(), static function (Document $document): bool {
            return $document->isCategory();
        });

        /** @var Document $parentCategory */
        foreach ($chain as $parentCategory) {
            if ($parentCategory->getCategoryId()) {
                $prevEntryId = $parentCategory->getCategoryId();

                continue;
            }

            $categoryHash = $parentCategory->getMetadata()->getHash();
            if ($mappedCategoryId = $this->categoryHandler->getEntityForHash($categoryHash)) {
                $prevEntryId = $mappedCategoryId;
            } else {
                $prevEntryId = $this->createCategory(
                    $parentCategory->getMetadata()->getTitleEn(),
                    $parentCategory->getMetadata()->getUrlEn(),
                    $parentCategory->getMetadata()->getTitleDe(),
                    $parentCategory->getMetadata()->getUrlDe(),
                    $prevEntryId
                );

                $this->categoryHandler->addEntryToMap($categoryHash, $prevEntryId);
            }

            $parentCategory->setCategoryId($prevEntryId);
        }

        return $prevEntryId;
    }

    private function createCategory(
        string $titleEn,
        string $seoEn,
        string $titleDe,
        string $seoDe,
        ?int $parentCategoryId = 50
    ): int {
        $response = $this->client->post(
            '/wiki/categories',
            [
                'json' => [
                    'id' => null,
                    'orderPriority' => null,
                    'active' => null,
                    'parent' => ['id' => $parentCategoryId],
                    'localizations' => [
                        [
                            'id' => null,
                            'locale' => [
                                'name' => 'de_DE',
                            ],
                            'title' => $titleDe,
                            'navigationTitle' => $titleDe,
                            'seoUrl' => $seoDe,
                            'content' => '',
                            'metaTitle' => '',
                            'metaDescription' => '',
                            'media' => null,
                            'searchableInAllLanguages' => false,
                        ],
                        [
                            'id' => null,
                            'locale' => [
                                'name' => 'en_GB',
                            ],
                            'title' => $titleEn,
                            'navigationTitle' => $titleEn,
                            'seoUrl' => $seoEn,
                            'content' => '',
                            'metaTitle' => '',
                            'metaDescription' => '',
                            'media' => null,
                            'searchableInAllLanguages' => true,
                        ],
                    ],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $responseJson = json_decode($responseContents, true);

        return $responseJson['id'];
    }

    private function createLocalizedVersionedArticle(string $seoEn, string $seoDe): array
    {
        $response = $this->client->post(
            '/wiki/entries',
            [
                'json' => [
                    'product' => ['id' => 4, 'name' => 'PF', 'label' => 'Shopware 6'],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $articleId = json_decode($responseContents, true)['id'];

        // create english lang
        $articleUrl = vsprintf('/wiki/entries/%d', [$articleId]);
        $articleLocalizationUrl = vsprintf('%s/localizations', [$articleUrl]);

        [$localeIdEn, $versionIdEn] = $this->createArticleLocale(
            $seoEn,
            $articleLocalizationUrl,
            ['id' => 2, 'name' => 'en_GB']
        );
        [$localeIdDe, $versionIdDe] = $this->createArticleLocale(
            $seoDe,
            $articleLocalizationUrl,
            ['name' => 'de_DE']
        );

        return [
            'en_GB' => [
                'articleId' => $articleId,
                'localeId' => $localeIdEn,
                'versionId' => $versionIdEn,
            ],
            'de_DE' => [
                'articleId' => $articleId,
                'localeId' => $localeIdDe,
                'versionId' => $versionIdDe,
            ],
        ];
    }

    private function createArticleLocale(string $seo, string $articleLocalizationUrl, array $locale): array
    {
        $response = $this->client->post(
            $articleLocalizationUrl,
            [
                'json' => [
                    'locale' => $locale, 'seoUrl' => $seo,
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $localeId = json_decode($responseContents, true)['id'];
        $articleInLocaleUrl = $articleLocalizationUrl . '/' . $localeId;
        $articleVersioningUrl = $articleInLocaleUrl . '/versions';

        // create version
        $response = $this->client->post(
            $articleVersioningUrl,
            [
                'json' => [
                    'version' => '1.0.0',
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $versionId = json_decode($responseContents, true)['id'];

        return [$localeId, $versionId];
    }

    private function deleteCategoryChildren(int $categoryId = -1): void
    {
        if ($categoryId === -1) {
            $categoryId = $this->rootCategoryId;
        }

        $categories = $this->getAllCategories();

        [$categoriesToDelete, $articlesToDelete] = $this->gatherCategoryChildrenAndArticles(
            $categoryId,
            $categories
        );

        foreach ($articlesToDelete as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
        }

        foreach (array_keys($categoriesToDelete) as $category) {
            $this->deleteCategory($category);
            $this->categoryHandler->deleteById($category);
        }
    }

    private function gatherCategoryChildrenAndArticles(int $rootId, array $categoryJson): array
    {
        $articleList = [];
        $categoryList = [];
        $idStack = [$rootId];

        while (\count($idStack) > 0) {
            $parentId = array_shift($idStack);

            foreach ($categoryJson as $category) {
                $parent = $category['parent'];
                if ($parent !== null && $parent['id'] === $parentId) {
                    /** @var array $localizations */
                    $localizations = $category['localizations'];

                    $seo = '';
                    foreach ($localizations as $locale) {
                        if ($locale['locale']['name'] === 'en_GB') {
                            $seo = $locale['seoUrl'];
                        }
                    }
                    $categoryList[$category['id']] = $seo;
                    $articleList[] = $category['entryIds'];
                    $idStack[] = $category['id'];
                }
            }
        }

        $categoryList = array_reverse($categoryList, true);

        // also delete articles in the root category
        $idColumn = array_column($categoryJson, 'id');
        $rootCategoryIndex = array_search($rootId, $idColumn, true);
        if ($rootCategoryIndex !== false) {
            $articleList[] = $categoryJson[$rootCategoryIndex]['entryIds'];
        }

        $articleList = array_unique(array_merge(...$articleList));

        return [$categoryList, $articleList];
    }

    private function deleteArticle(int $articleId): void
    {
        $this->client->delete(
            vsprintf('/wiki/entries/%d', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    private function disableArticle($articleId): void
    {
        $response = $this->client->get(
            vsprintf('/wiki/entries/%s', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        )->getBody()->getContents();
        $responseJson = json_decode($response, true);

        if (!\array_key_exists('localizations', $responseJson) || $responseJson['localizations'] === null) {
            return;
        }

        foreach ($responseJson['localizations'] as $locale) {
            $localId = $locale['id'];

            if (!\array_key_exists('versions', $locale) || $locale['versions'] === null) {
                continue;
            }

            foreach ($locale['versions'] as $version) {
                $versionId = $version['id'];
                $this->updateArticleVersion(
                    ['articleId' => $articleId, 'localeId' => $localId, 'versionId' => $versionId],
                    ['active' => false]
                );
            }
        }
    }

    private function deleteCategory(string $categoryId): void
    {
        $this->client->delete(
            vsprintf('/wiki/categories/%s', [$categoryId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    private function syncArticles(DocumentTree $tree): void
    {
        $i = 0;
        /** @var Document $document */
        foreach ($tree->getArticles() as $document) {
            ++$i;
            echo 'Syncing article (' . $i . '/' . \count($tree->getArticles()) . ') ' . $document->getFile()->getRelativePathname() . ' with priority ' . $document->getPriority() . PHP_EOL;

            $documentMetadata = $document->getMetadata();

            $hash = $documentMetadata->getHash();
            if ($articleId = $this->articleHandler->getEntityForHash($hash)) {
                $articleInfo = $this->getArticleInfo($articleId);
            } else {
                $articleInfo = $this->createLocalizedVersionedArticle(
                    $documentMetadata->getUrlEn(),
                    $documentMetadata->getUrlDe()
                )['en_GB'];

                $this->articleHandler->addEntryToMap($hash, $articleInfo['articleId']);
            }

            $categoryId = $this->getOrCreateMissingCategoryTree($document);
            $this->replaceCategoryForArticle($articleInfo, $categoryId);

            // handle media files for articles
            $images = $document->getHtml()->render($tree)->getImages();
            $imageMap = [];
            if (\count($images)) {
                echo '=> Uploading ' . \count($images) . ' media file(s) ...' . PHP_EOL;
                foreach ($images as $key => $mediaFile) {
                    $imageMap[$key] = $this->uploadArticleMedia($articleInfo, $mediaFile);
                }
            }

            $this->updateArticleLocale(
                $articleInfo,
                [
                    'seoUrl' => $documentMetadata->getUrlEn(),
                    'searchableInAllLanguages' => true,
                ]
            );
            $this->updateArticleVersion(
                $articleInfo,
                [
                    'content' => $document->getHtml()->render($tree)->getContents($imageMap),
                    'title' => $documentMetadata->getTitleEn(),
                    'navigationTitle' => $documentMetadata->getTitleEn(),
                    'searchableInAllLanguages' => true,
                    'active' => $documentMetadata->isActive(),
                    'fromProductVersion' => self::INITIAL_VERSION,
                    'metaTitle' => $documentMetadata->getMetaTitleEn(),
                    'metaDescription' => $documentMetadata->getMetaDescriptionEn(),
                ]
            );

            $this->updateArticlePriority($articleInfo, $document->getPriority());
        }
    }

    private function syncCategories(DocumentTree $tree): void
    {
        echo 'Syncing ' . \count($tree->getCategories()) . ' categories ...' . PHP_EOL;

        $this->addEmptyCategories($tree);
        $this->syncCategoryContents($tree);
    }

    private function addEmptyCategories(DocumentTree $tree): void
    {
        foreach ($tree->getCategories() as $document) {
            if ($document->getCategoryId()) {
                continue;
            }

            $this->getOrCreateMissingCategoryTree($document);
        }
    }

    private function syncCategoryContents(DocumentTree $tree): void
    {
        $oldCategories = $this->getAllCategories();
        $categoryIds = array_column($oldCategories, 'id');

        foreach ($tree->getCategories() as $document) {
            echo 'Syncing category ' . $document->getFile()->getRelativePathname() . ' with priority ' . $document->getPriority() . ' ... ' . PHP_EOL;
            $parentId = $this->rootCategoryId;
            $categoryId = $document->getCategoryId();

            if ($document->getParent()) {
                $parentId = $document->getParent()->getCategoryId();
            }

            if (!$categoryId) {
                echo 'Skipping category ' . $document->getFile()->getRelativePathname() . " - no sync reason found\n";

                continue;
            }

            if (!$parentId) {
                echo 'Skipping category ' . $document->getFile()->getRelativePathname() . " - parent not synced\n";

                continue;
            }

            $baseContents = $oldCategories[array_search($categoryId, $categoryIds, true)];

            if (!$baseContents) {
                throw new \RuntimeException('Unable to update category, no contents found');
            }

            $this->updateCategory(
                $categoryId,
                $parentId,
                $baseContents,
                $document,
                $tree
            );
        }
    }

    private function syncRoot(DocumentTree $tree): void
    {
        $root = $tree->getRoot();

        $oldCategories = $this->getAllCategories();
        $categoryIds = array_column($oldCategories, 'id');

        $index = array_search($this->rootCategoryId, $categoryIds, true);
        /** @var array[] $category */
        $category = $oldCategories[$index];

        $enIndex = -1;

        foreach ($category['localizations'] as $index => $localization) {
            if ($localization['locale']['name'] === 'en_GB') {
                $enIndex = $index;
            }
        }

        $category['localizations'][$enIndex]['content'] = $root->getHtml()->render($tree)->getContents();

        $this->client->put(
            vsprintf('/wiki/categories/%d', [$this->rootCategoryId]),
            [
                'json' => $category,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    private function getArticleInfo(int $articleId): array
    {
        $response = $this->client->get(
            sprintf('/wiki/entries/%d', $articleId),
            [
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseJson = json_decode($response->getBody()->getContents(), true);

        return [
            'articleId' => $articleId,
            'localeId' => $responseJson['localizations'][0]['id'],
            'versionId' => $responseJson['localizations'][0]['versions'][0]['id'],
        ];
    }

    private function removeAllPreviousCategories(int $articleId): void
    {
        $response = $this->client->get(
            sprintf('/wiki/entries/%d', $articleId),
            [
                'headers' => $this->getBasicHeaders(),
            ]
        );
        $body = json_decode($response->getBody()->getContents(), true);

        foreach ($body['categories'] as $category) {
            $this->client->delete(
                vsprintf(
                    '/wiki/categories/%d/entries/%d',
                    [
                        $category['id'],
                        $articleId,
                    ]
                ),
                [
                    'headers' => $this->getBasicHeaders(),
                ]
            );
        }
    }

    /**
     * Removes categories and articles that apparently got deleted.
     */
    private function removeDeletedEntities(DocumentTree $tree): void
    {
        $this->removeDeletedArticles($tree->getArticles());
        $this->removeDeletedCategories($tree->getCategories());
    }

    /**
     * @param Document[] $articles
     */
    private function removeDeletedArticles(array $articles): void
    {
        $hashesOnServer = $this->buildAssocArray($this->articleHandler->getAllEntityHashes());

        foreach ($articles as $article) {
            unset($hashesOnServer[$article->getMetadata()->getHash()]);
        }

        if (!$hashesOnServer) {
            return;
        }

        foreach ($hashesOnServer as $hashToBeDeleted => $mappedId) {
            $this->articleHandler->deleteEntityHash($hashToBeDeleted);
            $this->deleteArticle($mappedId);
        }
    }

    /**
     * @param Document[] $categories
     */
    private function removeDeletedCategories(array $categories): void
    {
        $hashesOnServer = $this->buildAssocArray($this->categoryHandler->getAllEntityHashes());

        foreach ($categories as $category) {
            unset($hashesOnServer[$category->getMetadata()->getHash()]);
        }

        if (!$hashesOnServer) {
            return;
        }

        foreach ($hashesOnServer as $hashToBeDeleted => $mappedId) {
            $this->categoryHandler->deleteEntityHash($hashToBeDeleted);
            $this->deleteCategory($mappedId);
        }
    }

    private function buildAssocArray(array $entities): array
    {
        $rebuiltArray = [];

        foreach ($entities as $entity) {
            $rebuiltArray[$entity['hash']] = $entity['mapped_id'];
        }

        return $rebuiltArray;
    }
}
