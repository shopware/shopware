<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

/**
 * TODO: test language fallback (currently only works in storefront api, but cant override language in storefront)
 */
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

        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));

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

        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => Defaults::LANGUAGE_EN]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($name, $responseData['data']['attributes']['name']);

        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($translatedName, $responseData['data']['attributes']['name']);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $langId);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertNull($responseData['data']['attributes']['name']);
    }

    private function assertTranslation($expectedTranslations, $translations, $langIdOverride = null): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();

        $categoryData = [
            'id' => $id,
            'translations' => $translations,
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists($this->getClient(), 'category', $id);

        $headers = [];
        if ($langIdOverride) {
            $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
            $headers = [$headerName => $langIdOverride];
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
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
            ];
            $this->getClient()->request('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode());
        }

        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
        ];
        $this->getClient()->request('POST', $baseUrl . '/language', $languageData);
        static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());
    }
}
