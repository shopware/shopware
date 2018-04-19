<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

class Shopware_Tests_Api_ArticleTest extends PHPUnit\Framework\TestCase
{
    public $apiBaseUrl = '';

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();

        $helper = Shopware();

        $hostname = $helper->Shop()->getHost();
        if (empty($hostname)) {
            $this->markTestSkipped(
                'Hostname is not available.'
            );
        }

        $this->apiBaseUrl = 'http://' . $hostname . $helper->Shop()->getBasePath() . '/api';
        Shopware()->Db()->query('UPDATE s_core_auth SET apiKey = ? WHERE username LIKE "demo"', [sha1('demo')]);
    }

    /**
     * @return Zend_Http_Client
     */
    public function getHttpClient()
    {
        $username = 'demo';
        $password = sha1('demo');

        $adapter = new Zend_Http_Client_Adapter_Curl();
        $adapter->setConfig([
            'curloptions' => [
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => "$username:$password",
            ],
        ]);

        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);

        return $client;
    }

    public function testRequestWithoutAuthenticationShouldReturnError()
    {
        $client = new Zend_Http_Client($this->apiBaseUrl . '/articles/');
        $response = $client->request('GET');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(401, $response->getStatus());

        $result = $response->getBody();

        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    public function testGetArticlesWithInvalidIdShouldReturnMessage()
    {
        $id = 99999999;
        $response = $this->getHttpClient()
                         ->setUri($this->apiBaseUrl . '/articles/' . $id)
                         ->request('GET');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(404, $response->getStatus());

        $result = $response->getBody();

        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    public function testPostArticlesShouldBeSuccessful()
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/');

        $requestData = [
            'name' => 'Testartikel',
            'description' => 'Test description',
            'descriptionLong' => 'Test descriptionLong',
            'active' => true,
            'pseudoSales' => 999,
            'highlight' => true,
            'keywords' => 'test, testarticle',

            'filterGroupId' => 1,

            'propertyValues' => [
                [
                    'value' => 'grün',
                    'option' => [
                        'name' => 'Farbe',
                    ],
                ],
                [
                    'value' => 'testWert',
                    'option' => [
                        'name' => 'neueOption' . uniqid(rand()),
                    ],
                ],
            ],

            'mainDetail' => [
                'number' => 'swTEST' . uniqid(rand()),
                'inStock' => 15,
                'unitId' => 1,

                'attribute' => [
                    'attr1' => 'Freitext1',
                    'attr2' => 'Freitext2',
                ],

                'minPurchase' => 5,
                'purchaseSteps' => 2,

                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 1,
                        'to' => 20,
                        'price' => 500,
                    ],
                    [
                        'customerGroupKey' => 'EK',
                        'from' => 21,
                        'to' => '-',
                        'price' => 400,
                    ],
                ],
            ],

            'configuratorSet' => [
                'name' => 'MeinKonf',
                'groups' => [
                    [
                        'name' => 'Farbe',
                        'options' => [
                            ['name' => 'Gelb'],
                            ['name' => 'Grün'],
                        ],
                    ],
                    [
                        'name' => 'Gräße',
                        'options' => [
                            ['name' => 'L'],
                            ['name' => 'XL'],
                        ],
                    ],
                ],
            ],

            'images' => [
                ['link' => 'http://assets.shopware.com/sw_logo_white.png'],
                ['link' => 'http://assets.shopware.com/sw_logo_white.png'],
            ],

            'variants' => [
                [
                    'number' => 'swTEST.variant.' . uniqid(rand()),
                    'inStock' => 17,
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],

                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],

                    'configuratorOptions' => [
                        [
                            'option' => 'Gelb',
                            'group' => 'Farbe',
                        ],
                        [
                            'option' => 'XL',
                            'group' => 'Größe',
                        ],
                    ],

                    'minPurchase' => 5,
                    'purchaseSteps' => 2,
                    'purchaseSteps' => 2,

                    'prices' => [
                        [
                            'customerGroupKey' => 'H',
                            'from' => 1,
                            'to' => 20,
                            'price' => 500,
                        ],
                        [
                            'customerGroupKey' => 'H',
                            'from' => 21,
                            'to' => '-',
                            'price' => 400,
                        ],
                    ],
                ],
                [
                    'number' => 'swTEST.variant.' . uniqid(rand()),
                    'inStock' => 17,
                    // create a new unit
                    'unit' => [
                        'unit' => 'xyz',
                        'name' => 'newUnit',
                    ],

                    'attribute' => [
                        'attr3' => 'Freitext3',
                        'attr4' => 'Freitext4',
                    ],

                    'configuratorOptions' => [
                        [
                            'option' => 'Grün',
                            'group' => 'Farbe',
                        ],
                        [
                            'option' => 'XL',
                            'group' => 'Größe',
                        ],
                    ],

                    'minPurchase' => 5,
                    'purchaseSteps' => 2,
                    'purchaseSteps' => 2,

                    'prices' => [
                        [
                            'customerGroupKey' => 'H',
                            'from' => 1,
                            'to' => 20,
                            'price' => 500,
                        ],
                        [
                            'customerGroupKey' => 'H',
                            'from' => 21,
                            'to' => '-',
                            'price' => 400,
                        ],
                    ],
                ],
            ],

            'taxId' => 1,
            'supplierId' => 2,

            'similar' => [
                ['id' => 5],
                ['id' => 6],
            ],

            'categories' => [
                ['id' => 15],
                ['id' => 10],
            ],

            'related' => [
                ['id' => 3, 'cross' => true],
                ['id' => 4],
            ],

            'links' => [
                ['name' => 'foobar', 'link' => 'http://example.org'],
                ['name' => 'Video', 'link' => 'http://example.org'],
            ],
        ];

        $requestData = Zend_Json::encode($requestData);
        $client->setRawData($requestData, 'application/json; charset=UTF-8');

        $response = $client->request('POST');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(201, $response->getStatus());
        $this->assertArrayHasKey('Location', $response->getHeaders());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $location = $response->getHeader('Location');
        $identifier = (int) array_pop(explode('/', $location));

        $this->assertGreaterThan(0, $identifier);

        return $identifier;
    }

    public function testPostArticlesWithInvalidDataShouldReturnError()
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/');

        $requestData = [
            'test' => true,
        ];
        $requestData = Zend_Json::encode($requestData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('POST');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(400, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     */
    public function testGetArticlesWithIdShouldBeSuccessful($id)
    {
        $response = $this->getHttpClient()
                         ->setUri($this->apiBaseUrl . '/articles/' . $id)
                         ->request('GET');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(200, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('data', $result);

        $data = $result['data'];
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('active', $data);

        $this->assertEquals('Testartikel', $data['name']);
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     */
    public function testPutArticlesWithInvalidDataShouldReturnError($id)
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

        // required field name is blank
        $testData = [
            'name' => ' ',
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',
        ];
        $requestData = Zend_Json::encode($testData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('PUT');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(400, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     */
    public function testPutArticlesShouldBeSuccessful($id)
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

        $testData = [
            'description' => 'Update description',
            'descriptionLong' => 'Update descriptionLong',

            // update supplier id
            'supplierId' => 3,

            // categories should be replaced
            'categories' => [
                ['id' => 16],
            ],

            'filterGroupId' => 1,

            // values should be replaced
            'propertyValues' => [
            ],

            // related is not included, therefore it stays untouched

            // similar is set to empty array, therefore it should be cleared
            'similar' => [],
        ];
        $requestData = Zend_Json::encode($testData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('PUT');

        $this->assertEquals(200, $response->getStatus());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertNull(
            $response->getHeader('Set-Cookie'),
            'There should be no set-cookie header set.'
        );
        $this->assertNull(
            $response->getHeader('location',
            'There should be no location header set.'
        ));

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('data', $result);

        $response = $this->getHttpClient()
                ->setUri($this->apiBaseUrl . '/articles/' . $id)
                ->request('GET');

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $article = $result['data'];

        $this->assertEquals($id, $article['id']);
        $this->assertEquals($testData['description'], $article['description']);
        $this->assertEquals($testData['descriptionLong'], $article['descriptionLong']);
        $this->assertEquals($testData['supplierId'], $article['supplier']['id']);

        // Categories should be updated
        $this->assertEquals(1, count($article['categories']));

        // Related should be untouched
        $this->assertEquals(2, count($article['related']));

        // Similar should be removed
        $this->assertEquals(0, count($article['similar']));
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     *
     * @param $id
     *
     * @throws Zend_Http_Client_Exception
     * @throws Zend_Json_Exception
     */
    public function testChangeVariantArticleMainVariantShouldBeSuccessful($id)
    {
        $response = $this->getHttpClient()
            ->setUri($this->apiBaseUrl . '/articles/' . $id)
            ->request('GET');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(200, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $variantNumbers = array_map(function ($item) {
            return $item['number'];
        }, $result['data']['details']);

        $oldMain = $result['data']['mainDetail']['number'];

        foreach ($variantNumbers as $variantNumber) {
            $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

            $testData = [
                'variants' => [
                    [
                        'number' => $variantNumber,
                        'isMain' => true,
                    ],
                ],
            ];
            $requestData = Zend_Json::encode($testData);

            $client->setRawData($requestData, 'application/json; charset=UTF-8');
            $response = $client->request('PUT');
            $this->assertEquals('application/json', $response->getHeader('Content-Type'));
            $this->assertEquals(null, $response->getHeader('Set-Cookie'));
            $this->assertEquals(200, $response->getStatus());
            $result = $response->getBody();
            $result = Zend_Json::decode($result);
            $this->assertArrayHasKey('success', $result);
            $this->assertTrue($result['success']);

            $response = $this->getHttpClient()
                ->setUri($this->apiBaseUrl . '/articles/' . $id)
                ->request('GET');
            $this->assertEquals('application/json', $response->getHeader('Content-Type'));
            $this->assertEquals(null, $response->getHeader('Set-Cookie'));
            $this->assertEquals(200, $response->getStatus());
            $result = $response->getBody();
            $result = Zend_Json::decode($result);

            $this->assertEquals($variantNumber, $result['data']['mainDetail']['number']);

            foreach ($result['data']['details'] as $variantData) {
                if ($variantData['number'] == $oldMain) {
                    $this->assertEquals(2, $variantData['kind']);
                }
            }

            $oldMain = $result['data']['mainDetail']['number'];
        }
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     */
    public function testReplaceArticleImagesWithUrlAndMediaId($articleId)
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $articleId);

        $requestData = [
            '__options_images' => [
                'replace' => 1,
            ],
            'images' => [
                [
                    'mediaId' => 44,
                ],
                [
                    'link' => 'http://assets.shopware.com/sw_logo_white.png',
                ],
                [
                    'mediaId' => 46,
                ],
            ],
        ];
        $requestData = Zend_Json::encode($requestData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('PUT');

        $this->assertEquals(200, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('data', $result);

        $data = $result['data'];
        $this->assertInternalType('array', $data);
        $this->assertEquals($articleId, $data['id']);
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     */
    public function testReplaceArticleImagesWithInvalidPayload($articleId)
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $articleId);

        $requestData = [
            '__options_images' => [
                'replace' => 1,
            ],
            'images' => [
                [
                    'id' => 999999,
                    'mediaId' => 44,
                ],
            ],
        ];
        $requestData = Zend_Json::encode($requestData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('PUT');

        $this->assertEquals(400, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    /**
     * @depends testPostArticlesShouldBeSuccessful
     *
     * @param $id
     *
     * @throws Zend_Http_Client_Exception
     * @throws Zend_Json_Exception
     *
     * @return
     */
    public function testDeleteArticlesShouldBeSuccessful($id)
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

        $response = $client->request('DELETE');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(200, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        return $id;
    }

    public function testDeleteArticlesWithInvalidIdShouldReturnMessage()
    {
        $id = 99999999;
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

        $response = $client->request('DELETE');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(404, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    public function testPutArticlesWithInvalidIdShouldReturnMessage()
    {
        $id = 99999999;
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/' . $id);

        $requestData = [
            'active' => true,
        ];
        $requestData = Zend_Json::encode($requestData);

        $client->setRawData($requestData, 'application/json; charset=UTF-8');
        $response = $client->request('PUT');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(404, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);

        $this->assertArrayHasKey('message', $result);
    }

    public function testGetArticlesShouldBeSuccessful()
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles');
        $result = $client->request('GET');

        $this->assertEquals('application/json', $result->getHeader('Content-Type'));
        $this->assertEquals(null, $result->getHeader('Set-Cookie'));
        $this->assertEquals(200, $result->getStatus());

        $result = $result->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertArrayHasKey('data', $result);

        $this->assertArrayHasKey('total', $result);
        $this->assertInternalType('int', $result['total']);
    }

    public function getSimpleArticleData()
    {
        return [
              'name' => 'Simple test article',
              'description' => 'Test description',
              'descriptionLong' => 'Test descriptionLong',
              'active' => true,
              'pseudoSales' => 999,
              'highlight' => true,
              'keywords' => 'test, testarticle',

              'filterGroupId' => 1,

              'propertyValues' => [
                  [
                      'value' => 'grün',
                      'option' => [
                          'name' => 'Farbe',
                      ],
                  ],
                  [
                      'value' => 'testWert',
                      'option' => [
                          'name' => 'neueOption' . uniqid(rand()),
                      ],
                  ],
              ],

              'mainDetail' => [
                  'number' => 'swTEST' . uniqid(rand()),
                  'inStock' => 15,
                  'unitId' => 1,

                  'attribute' => [
                      'attr1' => 'Freitext1',
                      'attr2' => 'Freitext2',
                  ],

                  'minPurchase' => 5,
                  'purchaseSteps' => 2,

                  'prices' => [
                      [
                          'customerGroupKey' => 'EK',
                          'from' => 1,
                          'to' => 20,
                          'price' => 500,
                      ],
                      [
                          'customerGroupKey' => 'EK',
                          'from' => 21,
                          'to' => '-',
                          'price' => 400,
                      ],
                  ],
              ],

              'taxId' => 1,
              'supplierId' => 2,

              'similar' => [
                  ['id' => 5],
                  ['id' => 6],
              ],

              'categories' => [
                  ['id' => 15],
                  ['id' => 10],
              ],

              'related' => [
                  ['id' => 3, 'cross' => true],
                  ['id' => 4],
              ],

              'links' => [
                  ['name' => 'foobar', 'link' => 'http://example.org'],
                  ['name' => 'Video', 'link' => 'http://example.org'],
              ],
          ];
    }

    public function testBatchModeShouldBeSuccessful()
    {
        $client = $this->getHttpClient()->setUri($this->apiBaseUrl . '/articles/');

        $data = [
            $this->getSimpleArticleData(),
            $this->getSimpleArticleData(),
            $this->getSimpleArticleData(),
            [
                'id' => 2,
                'keywords' => 'batch test',
            ],
        ];

        $requestData = Zend_Json::encode($data);
        $client->setRawData($requestData, 'application/json; charset=UTF-8');

        $response = $client->request('PUT');

        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(null, $response->getHeader('Set-Cookie'));
        $this->assertEquals(200, $response->getStatus());

        $result = $response->getBody();
        $result = Zend_Json::decode($result);

        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);

        $this->assertEquals('create', $result['data'][0]['operation']);
        $this->assertEquals('create', $result['data'][1]['operation']);
        $this->assertEquals('create', $result['data'][2]['operation']);
        $this->assertEquals('update', $result['data'][3]['operation']);
    }
}
