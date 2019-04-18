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
     * @var string
     */
    private $serverAddress;
    /**
     * @var string
     */
    private $rootCategoryId;

    public function __construct(string $sbpToken, string $serverAddress, int $rootCategoryId)
    {
        $this->sbpToken = $sbpToken;
        $this->serverAddress = $serverAddress;
        $this->rootCategoryId = $rootCategoryId;

        $this->client = new Client(['base_uri' => $this->serverAddress]);
    }

    public function getRootCategoryId()
    {
        return $this->rootCategoryId;
    }

    public function syncFilesWithServer(DocumentTree $tree): void
    {
        echo 'Syncing markdownfiles ...' . PHP_EOL;
        [$globalCategoryList, $articleList] = $this->gatherCategoryChildrenAndArticles($this->rootCategoryId, $this->getAllCategories());

        echo 'Deleting ' . count($articleList) . ' old articles ...' . PHP_EOL;
        foreach ($articleList as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
        }

        $this->deleteCategoryChildren();

        $this->syncArticles($tree);
        $this->syncCategories($tree);
        $this->syncRoot($tree);
    }

    private function insertGermanStubArticle(array $articleInfoDe, Document $document): void
    {
        $this->updateArticleLocale($articleInfoDe,
            [
                'seoUrl' => $document->getMetadata()->getUrlDe(),
                'searchableInAllLanguages' => true,
            ]
        );

        $this->updateArticleVersion($articleInfoDe,
            [
                'title' => $document->getMetadata()->getTitleDe(),
                'navigationTitle' => $document->getMetadata()->getTitleDe(),
                'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
                'searchableInAllLanguages' => true,
                'fromProductVersion' => self::INITIAL_VERSION,
                'active' => $document->getMetadata()->isActive(),
                'metaTitle' => $document->getMetadata()->getMetaTitleDe(),
                'metaDescription' => $document->getMetadata()->getMetaDescriptionDe(),
            ]);

        $this->updateArticlePriority($articleInfoDe, $document->getPriority());
    }

    private function buildArticleVersionUrl(array $articleInfo)
    {
        return vsprintf('/wiki/entries/%d/localizations/%d/versions/%d',
            [
                $articleInfo['articleId'],
                $articleInfo['localeId'],
                $articleInfo['versionId'],
            ]);
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
        $articleLocalUrl = vsprintf('/wiki/entries/%d/localizations/%d',
            [
                $articleInfo['articleId'],
                $articleInfo['localeId'],
            ]
        );

        $response = $this->client->get(
            $articleLocalUrl, ['headers' => $this->getBasicHeaders()]
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
        $response = $this->client->get('/wiki/categories',
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
        if (count($images)) {
            echo '=> Uploading ' . count($images) . ' mediafile(s) ...' . PHP_EOL;
            foreach ($images as $key => $mediaFile) {
                $mediaLink = $this->uploadCategoryMedia($categoryId, $oldContentEn['id'], $mediaFile);
                $imageMap[$key] = $mediaLink;
            }
        }

        $payloadGlobal = [
            'orderPriority' => $document->getPriority(),
            'active' => $document->getMetadata()->isActive(),
        ];

        $payloadEn = [
            'title' => $document->getMetadata()->getTitleEn(),
            'navigationTitle' => $document->getMetadata()->getTitleEn(),
            'content' => $document->getHtml()->render($tree)->getContents($imageMap),
            'searchableInAllLanguages' => true,
            'seoUrl' => $document->getMetadata()->getUrlEn(),
            'metaDescription' => $document->getMetadata()->getMetaDescriptionEn(),
        ];

        $payloadDe = [
            'title' => $document->getMetadata()->getTitleDe(),
            'navigationTitle' => $document->getMetadata()->getTitleDe(),
            'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
            'searchableInAllLanguages' => true,
            'seoUrl' => $document->getMetadata()->getUrlDe(),
            'metaDescription' => $document->getMetadata()->getMetaDescriptionDe(),
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

    private function addArticleToCategory(array $articleInfo, int $categoryId): void
    {
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

        $chain = array_filter($document->createParentChain(), function (Document $document): bool {
            return $document->isCategory();
        });

        /** @var Document $parentCategory */
        foreach ($chain as $parentCategory) {
            if ($parentCategory->getCategoryId()) {
                $prevEntryId = $parentCategory->getCategoryId();
                continue;
            }

            $prevEntryId = $this->createCategory(
                $parentCategory->getMetadata()->getTitleEn(),
                $parentCategory->getMetadata()->getUrlEn(),
                $parentCategory->getMetadata()->getTitleDe(),
                $parentCategory->getMetadata()->getUrlDe(),
                $prevEntryId
            );
            $parentCategory->setCategoryId($prevEntryId);
        }

        return $prevEntryId;
    }

    private function createCategory($titleEn, $seoEn, $titleDe, $seoDe, $parentCategoryId = 50): int
    {
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
                            'searchableInAllLanguages' => false,
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

    private function createLocalizedVersionedArticle($seoEn, $seoDe): array
    {
        $response = $this->client->post(
            '/wiki/entries',
            [
                'json' => [
                    'product' => ['id' => 4, 'name' => 'PF', 'label' => 'Shopware Platform'],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $articleId = json_decode($responseContents, true)['id'];

        // create english lang
        $articleUrl = vsprintf('/wiki/entries/%d', [$articleId]);
        $articleLocalizationUrl = vsprintf('%s/localizations', [$articleUrl]);

        [$localeIdEn, $versionIdEn, $articleUrlEn] = $this->createArticleLocale($seoEn, $articleLocalizationUrl, ['id' => 2, 'name' => 'en_GB']);
        [$localeIdDe, $versionIdDe, $articleUrlDe] = $this->createArticleLocale($seoDe, $articleLocalizationUrl, ['name' => 'de_DE']);

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

    private function createArticleLocale($seo, $articleLocalizationUrl, $locale): array
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
        $articleInLocaleWithVersionUrl = $articleVersioningUrl . '/' . $versionId;

        return [$localeId, $versionId, $articleInLocaleWithVersionUrl];
    }

    private function deleteCategoryChildren(int $categoryId = -1): void
    {
        if ($categoryId === -1) {
            $categoryId = $this->rootCategoryId;
        }

        $categories = $this->getAllCategories();

        [$categoriesToDelete, $articlesToDelete] = $this->gatherCategoryChildrenAndArticles(
            $categoryId,
            $categories);

        foreach ($articlesToDelete as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
        }

        foreach (array_keys($categoriesToDelete) as $category) {
            $this->deleteCategory($category);
        }
    }

    private function gatherCategoryChildrenAndArticles($rootId, $categoryJson): array
    {
        $articleList = [];
        $categoryList = [];
        $idStack = [$rootId];

        while (\count($idStack) > 0) {
            $parentId = array_shift($idStack);

            foreach ($categoryJson as $category) {
                $parent = $category['parent'];
                if ($parent !== null && $parent['id'] === $parentId) {
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

        $articleList = array_merge(...$articleList);
        $articleList = array_unique($articleList);

        return [$categoryList, $articleList];
    }

    private function deleteArticle($articleId): void
    {
        $this->client->delete(
            vsprintf('/wiki/entries/%s', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    private function disableArticle($articleId): void
    {
        $response = $this->client->get(
            vsprintf('/wiki/entries/%s', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        )->getBody()->getContents();
        $reponseJson = json_decode($response, true);

        if (!array_key_exists('localizations', $reponseJson) && $reponseJson['localizations'] === null) {
            return;
        }

        foreach ($reponseJson['localizations'] as $locale) {
            $localId = $locale['id'];

            if (!array_key_exists('versions', $locale) && $locale['versions'] === null) {
                continue;
            }

            foreach ($locale['versions'] as $version) {
                $versionId = $version['id'];
                $this->updateArticleVersion(['articleId' => $articleId, 'localeId' => $localId, 'versionId' => $versionId],
                    ['active' => false]);
            }
        }
    }

    private function deleteCategory($categoryId): void
    {
        $this->client->delete(
            vsprintf('/wiki/categories/%s', [$categoryId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    private function syncArticles(DocumentTree $tree)
    {
        $i = 0;
        /** @var Document $document */
        foreach ($tree->getArticles() as $document) {
            ++$i;
            echo 'Syncing article (' . $i . '/' . count($tree->getArticles()) . ') ' . $document->getFile()->getRelativePathname() . ' with prio ' . $document->getPriority() . PHP_EOL;

            $articleInfo = $this->createLocalizedVersionedArticle($document->getMetadata()->getUrlEn(), $document->getMetadata()->getUrlDe());
            $categoryId = $this->getOrCreateMissingCategoryTree($document);
            $this->addArticleToCategory($articleInfo['en_GB'], $categoryId);

            // handle media files for articles
            $images = $document->getHtml()->render($tree)->getImages();
            $imageMap = [];
            if (count($images)) {
                echo '=> Uploading ' . count($images) . ' mediafile(s) ...' . PHP_EOL;
                foreach ($images as $key => $mediaFile) {
                    $mediaLink = $this->uploadArticleMedia($articleInfo['en_GB'], $mediaFile);
                    $imageMap[$key] = $mediaLink;
                }
            }

            $this->updateArticleLocale($articleInfo['en_GB'],
                [
                    'seoUrl' => $document->getMetadata()->getUrlEn(),
                    'searchableInAllLanguages' => true,
                ]
            );
            $this->updateArticleVersion($articleInfo['en_GB'],
                [
                    'content' => $document->getHtml()->render($tree)->getContents($imageMap),
                    'title' => $document->getMetadata()->getTitleEn(),
                    'navigationTitle' => $document->getMetadata()->getTitleEn(),
                    'searchableInAllLanguages' => true,
                    'active' => $document->getMetadata()->isActive(),
                    'fromProductVersion' => self::INITIAL_VERSION,
                    'metaTitle' => $document->getMetadata()->getMetaTitleEn(),
                    'metaDescription' => $document->getMetadata()->getMetaDescriptionEn(),
                ]
            );

            $this->updateArticlePriority($articleInfo['en_GB'], $document->getPriority());
            $this->insertGermanStubArticle($articleInfo['de_DE'], $document);
        }
    }

    private function syncCategories(DocumentTree $tree): void
    {
        echo 'Syncing ' . count($tree->getCategories()) . ' categories ...' . PHP_EOL;

        $this->addEmptyCategories($tree);
        $this->syncCategoryContents($tree);
    }

    private function addEmptyCategories(DocumentTree $tree)
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

        /** @var Document $document */
        foreach ($tree->getCategories() as $document) {
            echo 'Syncing category ' . $document->getFile()->getRelativePathname() . ' with prio ' . $document->getPriority() . ' ... ' . PHP_EOL;
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

    private function syncRoot(DocumentTree $tree)
    {
        $root = $tree->getRoot();

        $oldCategories = $this->getAllCategories();
        $categoryIds = array_column($oldCategories, 'id');

        $index = array_search($this->rootCategoryId, $categoryIds, true);
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
}
