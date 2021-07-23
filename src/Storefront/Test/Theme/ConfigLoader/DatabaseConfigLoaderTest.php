<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

class DatabaseConfigLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider configurationLoadingProvider
     */
    public function testConfigurationLoading(string $key, array $config, array $expected): void
    {
        $ids = new IdsCollection();

        $themes = [
            [
                'id' => $ids->get('base'),
                'name' => 'base',
                'author' => 'test',
                'technicalName' => 'base',
                'active' => true,
                'baseConfig' => [
                    'fields' => $config['base'] ?? [],
                ],
            ],
            [
                'id' => $ids->get('parent'),
                'parentThemeId' => $ids->get('base'),
                'name' => 'parent',
                'author' => 'test',
                'active' => true,
                'technicalName' => 'parent',
                'baseConfig' => [
                    'fields' => $config['parent'] ?? [],
                ],
            ],
            [
                'id' => $ids->get('child'),
                'parentThemeId' => $ids->get('parent'),
                'name' => 'child',
                'author' => 'test',
                'active' => true,
                'technicalName' => 'child',
                'baseConfig' => [
                    'fields' => $config['child'] ?? [],
                ],
            ],
        ];

        $this->getContainer()->get('theme.repository')
            ->create($themes, Context::createDefaultContext());

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('base'),
            new StorefrontPluginConfiguration('parent'),
            new StorefrontPluginConfiguration('child'),
        ]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);

        $registry->method('getConfigurations')
            ->willReturn($collection);

        $service = new DatabaseConfigLoader(
            $this->getContainer()->get('theme.repository'),
            $registry,
            $this->getContainer()->get('media.repository'),
        );

        $config = $service->load($ids->get($key), Context::createDefaultContext());

        static::assertInstanceOf(StorefrontPluginConfiguration::class, $config);

        $fields = $config->getThemeConfig()['fields'];

        foreach ($expected as $field => $value) {
            static::assertArrayHasKey($field, $fields);
            static::assertEquals($value, $fields[$field]['value']);
        }
    }

    public function configurationLoadingProvider()
    {
        yield 'Test simple inheritance' => [
            'child',
            [
                'base' => [
                    'base-field-1' => self::field('#000'),
                ],
                'parent' => [
                    'parent-field-1' => self::field('#000'),
                ],
                'child' => [
                    'child-field-1' => self::field('#000'),
                ],
            ],
            [
                'base-field-1' => '#000',
                'parent-field-1' => '#000',
                'child-field-1' => '#000',
            ],
        ];

        yield 'Test overwrite' => [
            'child',
            [
                'base' => [
                    'base-field-1' => self::field('#000'),
                ],
                'parent' => [
                    'parent-field-1' => self::field('#000'),
                ],
                'child' => [
                    'parent-field-1' => self::field('#fff'),
                    'child-field-1' => self::field('#000'),
                ],
            ],
            [
                'base-field-1' => '#000',
                'parent-field-1' => '#fff',
                'child-field-1' => '#000',
            ],
        ];

        yield 'Test without inheritance' => [
            'base',
            [
                'base' => [
                    'base-field-1' => self::field('#000'),
                ],
                'parent' => [
                    'parent-field-1' => self::field('#000'),
                ],
                'child' => [
                    'parent-field-1' => self::field('#fff'),
                    'child-field-1' => self::field('#000'),
                ],
            ],
            [
                'base-field-1' => '#000',
            ],
        ];
    }

    private static function field(string $value): array
    {
        return ['type' => 'color', 'value' => $value];
    }
}
