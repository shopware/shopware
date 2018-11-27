<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class TranslationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testNoOverride(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                Defaults::LANGUAGE_EN => ['name' => 'not translated'],
                $langId => ['name' => 'translated'],
            ]
        );
    }

    public function testFallback(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            ['name' => 'translated by fallback'],
            [
                $fallbackId => ['name' => 'translated by fallback'],
            ],
            $langId
        );
    }

    public function testDefaultFallback(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                Defaults::LANGUAGE_EN => ['name' => 'translated by default fallback'],
            ]
        );
    }

    public function testEmptyLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = '';

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', '');
        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testInvalidUuidLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = 'foobar';

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', 'foobar');
        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testNonExistingLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = Uuid::uuid4()->getHex();

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', Uuid::uuid4()->getHex());
        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testOverride(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                Defaults::LANGUAGE_EN => ['name' => 'not translated'],
                $langId => ['name' => 'translated'],
            ],
            $langId
        );
    }

    public function testOverrideWithExtendedParams(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                Defaults::LANGUAGE_EN => ['name' => 'not translated'],
                $langId => ['name' => 'translated'],
            ],
            ['id' => $langId, 'inherit' => true]
        );
    }

    public function testNoDefaultTranslation(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                $langId => ['name' => 'translated'],
            ],
            $langId
        );
    }

    public function testExplicitDefaultTranslation(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                Defaults::LANGUAGE_EN => ['name' => 'not translated'],
                $langId => ['name' => 'translated'],
            ],
            Defaults::LANGUAGE_EN
        );
    }

    public function testPartialTranslationWithFallback(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => 'translated',
                'metaTitle' => 'translated by fallback',
            ],
            [
                $langId => [
                    'name' => 'translated',
                ],
                $fallbackId => [
                    'name' => 'translated by fallback',
                    'metaTitle' => 'translated by fallback',
                ],
            ],
            $langId
        );
    }

    public function testPartialTranslationWithFallbackNoInherit(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => 'translated',
                'metaTitle' => null,
            ],
            [
                $langId => [
                    'name' => 'translated',
                ],
                $fallbackId => [
                    'name' => 'translated by fallback',
                    'metaTitle' => 'translated by fallback',
                ],
            ],
            ['id' => $langId, 'inherit' => false]
        );
    }

    public function testChildTranslationWithoutRequiredField(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'metaTitle' => 'translated',
                'name' => 'only translated by fallback',
            ],
            [
                $langId => [
                    'metaTitle' => 'translated',
                ],
                $fallbackId => [
                    'name' => 'only translated by fallback',
                ],
            ],
            $langId
        );
    }

    public function testChildTranslationLongText(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'metaDescription' => 'translated',
                'name' => 'only translated by fallback',
            ],
            [
                $langId => [
                    'metaDescription' => 'translated',
                ],
                $fallbackId => [
                    'name' => 'only translated by fallback',
                ],
            ],
            $langId
        );
    }

    public function testWithOverrideInPost(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();

        $name = $id;
        $translatedName = $name . '_translated';

        $categoryData = [
            'id' => $id,
            'name' => $translatedName,
        ];

        $this->createLanguage($langId);

        $headerName = $this->getLangHeaderName();

        $this->getClient()->request('POST', $baseResource, $categoryData, [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists($this->getClient(), 'category', $id);

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent());
        static::assertEquals($translatedName, $responseData->data->attributes->name);
    }

    public function testDelete(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();

        $name = 'Test category';
        $translatedName = $name . '_translated';

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_EN => ['name' => $name],
                $langId => ['name' => $translatedName],
            ],
        ];

        $this->createLanguage($langId);

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $headerName = $this->getLangHeaderName();

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => Defaults::LANGUAGE_EN]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($name, $responseData['data']['attributes']['name']);

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($translatedName, $responseData['data']['attributes']['name']);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $langId);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals(null, $responseData['data']['attributes']['name']);
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    private function assertTranslation(array $expectedTranslations, array $translations, $langOverride = null): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();

        $categoryData = [
            'id' => $id,
            'translations' => $translations,
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($this->getClient(), 'category', $id);

        $headers = [];
        if ($langOverride) {
            $headerName = $this->getLangHeaderName();

            if (\is_array($langOverride)) {
                $params = 'id=' . $langOverride['id'] . ';';
                $params .= isset($langOverride['inherit']) ? ('inherit=' . (int) $langOverride['inherit']) : '';
                $langOverride = $params;
            }

            $headers = [$headerName => $langOverride];
        }

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], $headers);

        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent());

        static::assertArraySubset($expectedTranslations, (array) $responseData->data->attributes);
    }

    private function createLanguage($langId, $fallbackId = null): void
    {
        $baseUrl = '/api/v' . PlatformRequest::API_VERSION;

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::uuid4()->getHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
            ];
            $this->getClient()->request('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::uuid4()->getHex();
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
        ];

        $this->getClient()->request('POST', $baseUrl . '/language', $languageData);
        static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
    }
}
