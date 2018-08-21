<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;

class TranslationTest extends ApiTestCase
{
    public function testNoOverride()
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            'not translated',
            [
                Defaults::LANGUAGE => 'not translated',
                $langId => 'translated',
            ],
            null
        );
    }

    public function testOverride()
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            'translated',
            [
                Defaults::LANGUAGE => 'not translated',
                $langId => 'translated',
            ],
            $langId
        );
    }

    public function testNoDefaultTranslation()
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            'translated',
            [
                $langId => 'translated',
            ],
            $langId
        );
    }

    public function testExplicitDefaultTranslation()
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            'not translated',
            [
                Defaults::LANGUAGE => 'not translated',
                $langId => 'translated',
            ],
            Defaults::LANGUAGE
        );
    }

    public function testWithOverrideInPost()
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

        $this->apiClient->request('POST', $baseResource, $categoryData, [], [$headerName => $langId]);
        $response = $this->apiClient->getResponse();
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists('category', $id);

        $this->apiClient->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->apiClient->getResponse();
        $responseData = json_decode($response->getContent());
        $this->assertEquals($translatedName, $responseData->data->attributes->name);
    }

    private function assertTranslation($expectedTranslation, $translations, $langIdOverride = null)
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();

        $translations = array_map(function ($t) {
            return ['name' => $t];
        }, $translations);

        $categoryData = [
            'id' => $id,
            'translations' => $translations,
        ];

        $this->apiClient->request('POST', $baseResource, $categoryData);
        $response = $this->apiClient->getResponse();

        $this->assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists('category', $id);

        $headers = [];
        if ($langIdOverride) {
            $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
            $headers = [$headerName => $langIdOverride];
        }

        $this->apiClient->request('GET', $baseResource . '/' . $id, [], [], $headers);

        $response = $this->apiClient->getResponse();
        $responseData = json_decode($response->getContent());

        $this->assertEquals($expectedTranslation, $responseData->data->attributes->name);
    }

    private function createLanguage($langId)
    {
        $baseUrl = '/api/v' . PlatformRequest::API_VERSION;
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'localeId' => Defaults::LOCALE,
        ];
        $this->apiClient->request('POST', $baseUrl . '/language', $languageData);

        $this->assertEquals(204, $this->apiClient->getResponse()->getStatusCode());
    }
}
