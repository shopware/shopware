<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class CustomSnippetFormatControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetSnippetsWithoutPlugins(): void
    {
        $url = '/api/_action/custom-snippet';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);

        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/first_name',
            'address/last_name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
        ], $content['data']);
    }

    public function testGetSnippetsWithPlugins(): void
    {
        $plugin = new BundleWithCustomSnippet(true, __DIR__ . '/Fixtures/BundleWithCustomSnippet');
        $pluginCollection = $this->getContainer()->get(KernelPluginCollection::class);
        $pluginCollection->add($plugin);

        $url = '/api/_action/custom-snippet';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);

        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/first_name',
            'address/last_name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
            'custom-snippet/custom-snippet',
        ], $content['data']);

        $originalCollection = $pluginCollection->filter(fn (Plugin $plugin) => $plugin->getName() !== 'BundleWithCustomSnippet');

        $pluginsProp = new \ReflectionProperty($pluginCollection, 'plugins');
        $pluginsProp->setAccessible(true);
        $pluginsProp->setValue($pluginCollection, $originalCollection->all());
    }

    /**
     * @param array{format: array<int, array<int, string>>, data: array<string, mixed>} $payload
     */
    #[DataProvider('renderProvider')]
    public function testRender(array $payload, string $expectedHtml): void
    {
        $url = '/api/_action/custom-snippet/render';
        $client = $this->getBrowser();
        $client->request('POST', $url, [], [], [], json_encode($payload, \JSON_THROW_ON_ERROR));

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('rendered', $content);
        static::assertEquals($expectedHtml, $content['rendered']);
    }

    /**
     * @return iterable<string, array<string, string|array<string, array<mixed>>>>
     */
    public static function renderProvider(): iterable
    {
        yield 'without data and format' => [
            'payload' => [
                'format' => [],
                'data' => [],
            ],
            'expectedHtml' => '',
        ];

        yield 'without data' => [
            'payload' => [
                'format' => [],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                    ],
                ],
            ],
            'expectedHtml' => '',
        ];

        yield 'without format' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                    ],
                ],
                'data' => [],
            ],
            'expectedHtml' => '',
        ];

        yield 'with data and format' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                    ],
                ],
            ],
            'expectedHtml' => 'Le Vin',
        ];

        yield 'render multiple lines' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                ],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                        'street' => '123 Strt',
                        'country' => [
                            'translated' => [
                                'name' => 'VN',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedHtml' => 'Le Vin<br/>123 Strt VN',
        ];

        yield 'render multiple lines with symbol' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                        'symbol/comma',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                ],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                        'street' => '123 Strt',
                        'country' => [
                            'translated' => [
                                'name' => 'VN',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedHtml' => 'Le Vin,<br/>123 Strt VN',
        ];

        yield 'render ignore empty snippet' => [
            'payload' => [
                'format' => [
                    [
                        'address/company',
                        'symbol/dash',
                        'address/department',
                    ],
                    [
                        'address/first_name',
                        'address/last_name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                        'company' => 'shopware AG',
                        'department' => '',
                    ],
                ],
            ],
            'expectedHtml' => 'shopware AG<br/>Vin Le',
        ];

        yield 'render ignore empty line' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                    [
                        'address/first_name',
                        'address/last_name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'firstName' => 'Vin',
                        'lastName' => 'Le',
                    ],
                ],
            ],
            'expectedHtml' => 'Le Vin<br/>Vin Le',
        ];

        yield 'render line with only concat symbol' => [
            'payload' => [
                'format' => [
                    [
                        'address/last_name',
                        'address/first_name',
                        'symbol/dash',
                    ],
                ],
                'data' => [
                    'address' => [],
                ],
            ],
            'expectedHtml' => '',
        ];

        yield 'render lines with symbol comma' => [
            'payload' => [
                'format' => [
                    [
                        'address/zipcode',
                        'symbol/comma',
                        'address/city',
                    ],
                ],
                'data' => [
                    'address' => [
                        'zipcode' => '550000',
                        'city' => 'Da Nang',
                    ],
                ],
            ],
            'expectedHtml' => '550000,  Da Nang',
        ];
    }
}

/**
 * @internal
 */
class BundleWithCustomSnippet extends Plugin
{
    public function getPath(): string
    {
        $reflected = new \ReflectionObject($this);

        return \dirname($reflected->getFileName() ?: '') . '/Fixtures/BundleWithCustomSnippet';
    }
}
