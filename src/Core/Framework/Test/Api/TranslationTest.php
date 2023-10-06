<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingSystemTranslationException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Language\TranslationValidator;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @group slow
 */
class TranslationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testNoOverride(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated', 'translated' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ]
        );
    }

    public function testDefault(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
                'translations' => [
                    $this->getDeDeLanguageId() => ['name' => 'german'],
                ],
            ]
        );
    }

    public function testDefault2(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
            ]
        );
    }

    public function testDefaultAndExplicitSystem(): void
    {
        $this->assertTranslation(
            ['name' => 'system'],
            [
                'name' => 'default',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                ],
            ]
        );
    }

    public function testFallback(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            ['name' => null, 'translated' => ['name' => 'translated by fallback']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $fallbackId => ['name' => 'translated by fallback'],
                ],
            ],
            $langId
        );
    }

    public function testDefaultFallback(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testWithLanguageIdParam(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    ['languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testOnlySystemLocaleIdentifier(): void
    {
        $localeRepo = $this->getContainer()->get('locale.repository');
        /** @var LocaleEntity $locale */
        $locale = $localeRepo->search(new Criteria([$this->getLocaleIdOfSystemLanguage()]), Context::createDefaultContext())->first();

        $this->assertTranslation(
            ['name' => 'system translation'],
            [
                'translations' => [
                    $locale->getCode() => ['name' => 'system translation'],
                ],
            ]
        );
    }

    public function testEmptyLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = '';

        $this->getBrowser()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(412, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testInvalidUuidLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = 'foobar';

        $this->getBrowser()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', 'foobar');
        $this->getBrowser()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testNonExistingLanguageIdError(): void
    {
        $baseResource = '/api/category';
        $headerName = $this->getLangHeaderName();
        $langId = Uuid::randomHex();

        $this->getBrowser()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', Uuid::randomHex());
        $this->getBrowser()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testOverride(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $langId
        );
    }

    public function testNoDefaultTranslation(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslationError(
            [
                [
                    'code' => MissingSystemTranslationException::VIOLATION_MISSING_SYSTEM_TRANSLATION,
                    'status' => '400',
                    'source' => [
                        'pointer' => '/0/translations/' . Defaults::LANGUAGE_SYSTEM,
                    ],
                ],
            ],
            [
                'translations' => [
                    $langId => ['name' => 'translated'],
                ],
            ]
        );
    }

    public function testExplicitDefaultTranslation(): void
    {
        $langId = Uuid::randomHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            Defaults::LANGUAGE_SYSTEM
        );
    }

    public function testPartialTranslationWithFallback(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => 'translated',
                'territory' => null,
                'translated' => [
                    'territory' => 'translated by fallback',
                ],
            ],
            [
                'code' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default', 'territory' => 'translated by default'],
                    $langId => [
                        'name' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'translated by fallback',
                        'territory' => 'translated by fallback',
                    ],
                ],
            ],
            $langId,
            'locale'
        );
    }

    public function testChildTranslationWithoutRequiredField(): void
    {
        $langId = Uuid::randomHex();
        $fallbackId = Uuid::randomHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => null,
                'territory' => 'translated',
                'translated' => [
                    'name' => 'only translated by fallback',
                    'territory' => 'translated',
                ],
            ],
            [
                'code' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default', 'territory' => 'translated by default'],
                    $langId => [
                        'territory' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'only translated by fallback',
                    ],
                ],
            ],
            $langId,
            'locale'
        );
    }

    public function testWithOverrideInPatch(): void
    {
        $baseResource = '/api/locale';
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $notTranslated = [
            'id' => $id,
            'code' => 'test',
            'name' => 'not translated',
            'territory' => 'not translated',
        ];

        $this->createLanguage($langId);

        $headerName = $this->getLangHeaderName();

        $this->getBrowser()->request('POST', $baseResource, $notTranslated);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists($this->getBrowser(), 'locale', $id);

        $translated = [
            'id' => $id,
            'name' => 'translated',
        ];

        $this->getBrowser()->request('PATCH', $baseResource . '/' . $id, $translated, [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->getBrowser()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($translated['name'], $responseData['data']['attributes']['name']);
        static::assertNull($responseData['data']['attributes']['territory']);

        static::assertEquals($notTranslated['territory'], $responseData['data']['attributes']['translated']['territory']);
    }

    public function testDelete(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $langId = Uuid::randomHex();

        $name = 'Test category';
        $translatedName = $name . '_translated';

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => $name],
                $langId => ['name' => $translatedName],
            ],
        ];

        $this->createLanguage($langId);

        $this->getBrowser()->request('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $headerName = $this->getLangHeaderName();

        $this->getBrowser()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => Defaults::LANGUAGE_SYSTEM]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($name, $responseData['data']['attributes']['name']);

        $this->getBrowser()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($translatedName, $responseData['data']['attributes']['name']);

        $this->getBrowser()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $langId);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($responseData['data']['attributes']['name']);
    }

    public function testDeleteSystemLanguageViolation(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];
        $this->getBrowser()->request('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->request('DELETE', $baseResource . '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(400, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals(TranslationValidator::VIOLATION_DELETE_SYSTEM_TRANSLATION, $data['errors'][0]['code']);
        static::assertEquals('/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM, $data['errors'][0]['source']['pointer']);
    }

    public function testDeleteEntityWithOneRootTranslation(): void
    {
        /**
         * This works because the dal does not generate a `DeleteCommand` for the `CategoryTranslation`.
         * The translation is delete by the foreign key delete cascade.
         */
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootId = Uuid::randomHex();

        $this->createLanguage($rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];

        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($categoryData, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->request('DELETE', $baseResource . '/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteNonSystemRootTranslations(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootDelete = Uuid::randomHex();
        $this->createLanguage($rootDelete);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootDelete => ['name' => 'root delete'],
            ],
        ];
        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($categoryData, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $rootDelete);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteChildLanguageTranslation(): void
    {
        $baseResource = '/api/category';
        $id = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->createLanguage($childId, $rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootId => ['name' => 'root'],
                $childId => ['name' => 'child'],
            ],
        ];
        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($categoryData, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getBrowser(), 'category', $id);

        $this->getBrowser()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $childId);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testMixedTranslationStatus(): void
    {
        $baseResource = '/api/category';
        $rootLangId = Uuid::randomHex();
        $childLangId = Uuid::randomHex();
        $this->createLanguage($childLangId, $rootLangId);

        $idSystem = Uuid::randomHex();
        $system = [
            'id' => $idSystem,
            'name' => '1. system',
        ];
        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($system, \JSON_THROW_ON_ERROR));
        $this->assertEntityExists($this->getBrowser(), 'category', $idSystem);

        $idRoot = Uuid::randomHex();
        $root = [
            'id' => $idRoot,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '2. system'],
                $rootLangId => ['name' => '2. root'],
            ],
        ];
        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($root, \JSON_THROW_ON_ERROR));
        $this->assertEntityExists($this->getBrowser(), 'category', $idRoot);

        $idChild = Uuid::randomHex();
        $childAndRoot = [
            'id' => $idChild,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '3. system'],
                $rootLangId => ['name' => '3. root'],
                $childLangId => ['name' => '3. child'],
            ],
        ];
        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($childAndRoot, \JSON_THROW_ON_ERROR));
        $this->assertEntityExists($this->getBrowser(), 'category', $idChild);

        $headers = [
            'HTTP_ACCEPT' => 'application/json',
            $this->getLangHeaderName() => $childLangId,
        ];
        $this->getBrowser()->request('GET', $baseResource . '?sort=name', [], [], $headers);
        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data'];

        static::assertNull($data[0]['name']);
        static::assertNull($data[1]['name']);
        static::assertEquals('3. child', $data[2]['name']);

        static::assertEquals('1. system', $data[0]['translated']['name']);
        static::assertEquals('2. root', $data[1]['translated']['name']);
        static::assertEquals('3. child', $data[2]['translated']['name']);
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    private function assertTranslationError(array $errors, array $data): void
    {
        $baseResource = '/api/category';

        $categoryData = [
            'id' => Uuid::randomHex(),
        ];
        $categoryData = array_merge_recursive($categoryData, $data);

        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($categoryData, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(400, $response->getStatusCode(), $response->getContent());

        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(\count($errors), $responseData['errors']);

        $actualErrors = array_map(function ($error) {
            $e = [
                'code' => $error['code'],
                'status' => $error['status'],
            ];
            if (isset($error['source'])) {
                $e['source'] = $error['source'];
            }

            return $e;
        }, $responseData['errors']);

        static::assertEquals($errors, $actualErrors);
    }

    private function assertTranslation(array $expectedTranslations, array $data, ?string $langOverride = null, string $entity = 'category'): void
    {
        $baseResource = '/api/' . $entity;

        $requestData = $data;
        if (!isset($requestData['id'])) {
            $requestData['id'] = Uuid::randomHex();
        }

        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($requestData, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($this->getBrowser(), $entity, $requestData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getBrowser()->request('GET', $baseResource . '/' . $requestData['id'], [], [], $headers);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $responseData = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $responseData, $response->getContent());
        foreach ($expectedTranslations as $key => $expectedTranslation) {
            if (!\is_array($expectedTranslations[$key])) {
                static::assertEquals($expectedTranslations[$key], $responseData['data'][$key]);
            } else {
                foreach ($expectedTranslations[$key] as $key2 => $expectedTranslation2) {
                    static::assertEquals($expectedTranslation[$key2], $responseData['data'][$key][$key2]);
                }
            }
        }
    }

    private function createLanguage(string $langId, ?string $fallbackId = null): void
    {
        $baseUrl = '/api';

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::randomHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
            ];
            $this->getBrowser()->request('POST', $baseUrl . '/language', [], [], [], json_encode($parentLanguageData, \JSON_THROW_ON_ERROR));
            static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::randomHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'x-tst_' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
        ];

        $this->getBrowser()->request('POST', $baseUrl . '/language', [], [], [], json_encode($languageData, \JSON_THROW_ON_ERROR));
        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->request('GET', $baseUrl . '/language/' . $langId);
    }
}
