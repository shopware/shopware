<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\ConfigLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
class DatabaseConfigLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MEDIA_ID = 'eac39bbb419e4741a950cd94f55b35ef';

    private IdsCollection $ids;

    private EntityRepository $themeRepository;

    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();

        $this->themeRepository = $this->getContainer()->get('theme.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
    }

    public function setUpMedia(): void
    {
        $this->ids->set('media', self::MEDIA_ID);

        $data = [
            'id' => $this->ids->get('media'),
            'fileName' => 'testImage',
            'mimeType' => 'image/png',
            'fileExtension' => 'png',
        ];

        $this->mediaRepository->create([$data], Context::createDefaultContext());
    }

    /**
     * NEXT-20034
     *
     * @group quarantined
     */
    public function testMediaConfigurationLoading(): void
    {
        $this->setUpMedia();

        $theme = [[
            'id' => $this->ids->get('base'),
            'name' => 'base',
            'author' => 'test',
            'technicalName' => 'base',
            'active' => true,
            'baseConfig' => [
                'fields' => [
                    'media-field' => self::media(self::MEDIA_ID),
                ],
            ],
        ]];

        $this->themeRepository->create($theme, Context::createDefaultContext());

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('base'),
        ]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')
            ->willReturn($collection);

        $service = new DatabaseConfigLoader(
            $this->themeRepository,
            $registry,
            $this->mediaRepository,
            'base',
        );

        $config = $service->load($this->ids->get('base'), Context::createDefaultContext());

        static::assertInstanceOf(StorefrontPluginConfiguration::class, $config);

        $themeConfig = $config->getThemeConfig();
        static::assertNotNull($themeConfig);

        $mediaURL = EnvironmentHelper::getVariable('APP_URL') . '/media/fd/01/0e/testImage.png';

        static::assertEquals($mediaURL, $themeConfig['fields']['media-field']['value'], 'If This Failes, please update NEXT-20034 and inform s.sluiter directly!');
    }

    public function testEmptyMediaConfigurationLoading(): void
    {
        $theme = [[
            'id' => $this->ids->get('base'),
            'name' => 'base',
            'author' => 'test',
            'technicalName' => 'base',
            'active' => true,
            'baseConfig' => [
                'fields' => [
                    'media-field' => self::media(null),
                ],
            ],
        ]];

        $this->themeRepository->create($theme, Context::createDefaultContext());

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('base'),
        ]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')
            ->willReturn($collection);

        $service = new DatabaseConfigLoader(
            $this->themeRepository,
            $registry,
            $this->mediaRepository,
            'base',
        );

        $config = $service->load($this->ids->get('base'), Context::createDefaultContext());

        static::assertInstanceOf(StorefrontPluginConfiguration::class, $config);

        $themeConfig = $config->getThemeConfig();
        static::assertNotNull($themeConfig);

        $mediaURL = null;

        static::assertEquals($mediaURL, $themeConfig['fields']['media-field']['value']);
    }

    public function testNonExistentMediaConfigurationLoading(): void
    {
        $theme = [[
            'id' => $this->ids->get('base'),
            'name' => 'base',
            'author' => 'test',
            'technicalName' => 'base',
            'active' => true,
            'baseConfig' => [
                'fields' => [
                    'media-field' => self::media(self::MEDIA_ID),
                ],
            ],
        ]];

        $this->themeRepository->create($theme, Context::createDefaultContext());

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('base'),
        ]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')
            ->willReturn($collection);

        $service = new DatabaseConfigLoader(
            $this->themeRepository,
            $registry,
            $this->mediaRepository,
            'base',
        );

        $config = $service->load($this->ids->get('base'), Context::createDefaultContext());

        static::assertInstanceOf(StorefrontPluginConfiguration::class, $config);

        $themeConfig = $config->getThemeConfig();
        static::assertNotNull($themeConfig);

        $mediaURL = self::MEDIA_ID;

        static::assertEquals($mediaURL, $themeConfig['fields']['media-field']['value']);
    }

    /**
     * @dataProvider configurationLoadingProvider
     *
     * @param array<string, mixed> $config
     * @param array<string|int, mixed> $expected>
     */
    public function testConfigurationLoading(string $key, array $config, array $expected): void
    {
        $themes = [
            [
                'id' => $this->ids->get('base'),
                'name' => 'base',
                'author' => 'test',
                'technicalName' => 'base',
                'active' => true,
                'baseConfig' => [
                    'fields' => $config['base'] ?? [],
                ],
            ],
            [
                'id' => $this->ids->get('parent'),
                'parentThemeId' => $this->ids->get('base'),
                'name' => 'parent',
                'author' => 'test',
                'active' => true,
                'technicalName' => 'parent',
                'baseConfig' => [
                    'fields' => $config['parent'] ?? [],
                ],
            ],
            [
                'id' => $this->ids->get('child'),
                'parentThemeId' => $this->ids->get('parent'),
                'name' => 'child',
                'author' => 'test',
                'active' => true,
                'technicalName' => 'child',
                'baseConfig' => [
                    'fields' => $config['child'] ?? [],
                ],
            ],
        ];

        $this->themeRepository->create($themes, Context::createDefaultContext());

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('base'),
            new StorefrontPluginConfiguration('parent'),
            new StorefrontPluginConfiguration('child'),
        ]);

        $registry = $this->createMock(StorefrontPluginRegistry::class);

        $registry->method('getConfigurations')
            ->willReturn($collection);

        $service = new DatabaseConfigLoader(
            $this->themeRepository,
            $registry,
            $this->mediaRepository,
            'base'
        );

        $config = $service->load($this->ids->get($key), Context::createDefaultContext());

        static::assertInstanceOf(StorefrontPluginConfiguration::class, $config);

        $themeConfig = $config->getThemeConfig();
        static::assertNotNull($themeConfig);
        $fields = $themeConfig['fields'];

        foreach ($expected as $field => $value) {
            static::assertArrayHasKey($field, $fields);
            static::assertEquals($value, $fields[$field]['value']);
        }
    }

    /**
     * @return iterable<int|string, mixed>
     */
    public static function configurationLoadingProvider(): iterable
    {
        yield 'Test simple inheritance' => [
            'child',
            [
                'base' => [
                    'base-field-1' => self::field('#000'),
                    'base-field-2' => self::fieldUntyped(7),
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
                    'base-field-1' => self::fieldUntyped('#000'),
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

    /**
     * @return array<string, string|null>
     */
    private static function field(string $value): array
    {
        return ['type' => 'color', 'value' => $value];
    }

    /**
     * @return array<string, string|null>
     */
    private static function media(?string $value): array
    {
        return ['type' => 'media', 'value' => $value];
    }

    /**
     * @return array<string, string|int>
     */
    private static function fieldUntyped(string|int $value): array
    {
        return ['value' => $value];
    }
}
