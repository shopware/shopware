<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Configuration::class)]
class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilder(): void
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTreeBuilder();

        static::assertSame('shopware', $tree->buildTree()->getName());
    }

    public function testFeatureToggleConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('feature_toggle', $nodes);
        $node = $nodes['feature_toggle'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('enable', $nodes);
        $node = $nodes['enable'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);
    }

    public function testCdnPathCacheBusterDefaultsToTrue(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['cdn' => []]]);

        static::assertTrue($config['cdn']['path_cache_buster']);
    }

    public function testTranslationConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('translation', $nodes);
        $node = $nodes['translation'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $children = $node->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $children['repository_url']);
        static::assertInstanceOf(ScalarNodeDefinition::class, $children['metadata_url']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $children['plugins']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $children['excluded_locales']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $children['plugin_mapping']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $children['languages']);
        static::assertInstanceOf(BooleanNodeDefinition::class, $children['use_local_filesystem']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $children['scheduled_task']);
        static::assertInstanceOf(BooleanNodeDefinition::class, $children['scheduled_task']->getChildNodeDefinitions()['enabled']);
    }

    public function testTranslationConfigRejectsInvalidListType(): void
    {
        $configuration = new Configuration();

        $this->expectExceptionObject(new InvalidConfigurationException(
            'Invalid type for path "shopware.translation.languages". Expected "array", but got "string"'
        ));

        (new Processor())->processConfiguration($configuration, [
            [
                'translation' => [
                    'languages' => 'foo',
                ],
            ],
        ]);
    }

    public function testTranslationConfigDefaultsToNull(): void
    {
        $configuration = new Configuration();

        $config = (new Processor())->processConfiguration($configuration, []);

        static::assertSame([
            'repository_url' => null,
            'metadata_url' => null,
            'community_translations_url' => null,
            'documentation_url_snippet_key' => null,
            'completeness_threshold' => null,
            'plugins' => null,
            'excluded_locales' => null,
            'pseudo_locales' => null,
            'plugin_mapping' => null,
            'languages' => null,
            'use_local_filesystem' => false,
            'scheduled_task' => [
                'enabled' => true,
            ],
        ], $config['translation']);
    }

    public function testTranslationConfigListOverrideReplacesPreviousValue(): void
    {
        $configuration = new Configuration();

        $config = (new Processor())->processConfiguration($configuration, [
            [
                'translation' => [
                    'plugins' => ['PluginA', 'PluginB'],
                    'excluded_locales' => ['de-DE'],
                ],
            ],
            [
                'translation' => [
                    'plugins' => ['PluginC'],
                    'excluded_locales' => [],
                ],
            ],
        ]);

        static::assertSame(['PluginC'], $config['translation']['plugins']);
        static::assertSame([], $config['translation']['excluded_locales']);
    }

    public function testFeatureConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('feature', $nodes);
        $node = $nodes['feature'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('flags', $nodes);
        $node = $nodes['flags'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $node = array_values($node->getChildNodeDefinitions())[0];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);
        $nodes = $node->getChildNodeDefinitions();

        static::assertArrayHasKey('name', $nodes);
        $node = $nodes['name'];
        static::assertInstanceOf(ScalarNodeDefinition::class, $node);

        static::assertArrayHasKey('description', $nodes);
        $node = $nodes['description'];
        static::assertInstanceOf(ScalarNodeDefinition::class, $node);

        static::assertArrayHasKey('major', $nodes);
        $node = $nodes['major'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);

        static::assertArrayHasKey('toggleable', $nodes);
        $node = $nodes['toggleable'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);

        static::assertArrayHasKey('default', $nodes);
        $node = $nodes['default'];
        static::assertInstanceOf(BooleanNodeDefinition::class, $node);
    }

    public function testHtmlSanitizerConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('html_sanitizer', $nodes);
        $node = $nodes['html_sanitizer'];
        static::assertInstanceOf(ArrayNodeDefinition::class, $node);

        $nodes = $node->getChildNodeDefinitions();

        static::assertInstanceOf(VariableNodeDefinition::class, $nodes['cache_dir']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['sets']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['fields']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes = $nodes['sets']->getChildNodeDefinitions()['']);
        $setsNodes = $setsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $setsNodes['name']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['tags']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['attributes']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['options']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $setsNodes['custom_attributes']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes = $setsNodes['custom_attributes']->getChildNodeDefinitions()['']);
        $customAttributeNodes = $customAttributeNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes['tags']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $customAttributeNodes['attributes']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $optionsNodes = $setsNodes['options']->getChildNodeDefinitions()['']);
        $optionsNodes = $optionsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $optionsNodes['key']);
        static::assertInstanceOf(ScalarNodeDefinition::class, $optionsNodes['value']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $optionsNodes['values']);

        static::assertInstanceOf(ArrayNodeDefinition::class, $fieldsNodes = $nodes['fields']->getChildNodeDefinitions()['']);
        $fieldsNodes = $fieldsNodes->getChildNodeDefinitions();
        static::assertInstanceOf(ScalarNodeDefinition::class, $fieldsNodes['name']);
        static::assertInstanceOf(ArrayNodeDefinition::class, $fieldsNodes['sets']);
    }

    public function testSearchTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('search', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $searchNode = $nodes['search']);

        $nodes = $searchNode->getChildNodeDefinitions();

        static::assertArrayHasKey('preserved_chars', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['preserved_chars']);
    }

    public function testSystemConfigTreeNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('system_config', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['system_config']);

        $nodes = $nodes['system_config']->getChildNodeDefinitions();

        static::assertArrayHasKey('', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['']);
    }

    public function testUsageDataSection(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('usage_data', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['usage_data']);

        $nodes = $nodes['usage_data']->getChildNodeDefinitions();
        static::assertArrayHasKey('collection_enabled', $nodes);
        static::assertInstanceOf(ScalarNodeDefinition::class, $nodes['collection_enabled']);

        static::assertArrayHasKey('gateway', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['gateway']);

        $nodes = $nodes['gateway']->getChildNodeDefinitions();
        static::assertArrayHasKey('dispatch_enabled', $nodes);
        static::assertInstanceOf(ScalarNodeDefinition::class, $nodes['dispatch_enabled']);

        static::assertArrayHasKey('base_uri', $nodes);
        static::assertInstanceOf(ScalarNodeDefinition::class, $nodes['base_uri']);

        static::assertArrayHasKey('batch_size', $nodes);
        static::assertInstanceOf(ScalarNodeDefinition::class, $nodes['batch_size']);
    }

    public function testProductAllowedTypesNode(): void
    {
        $configuration = new Configuration();

        $rootNode = $configuration->getConfigTreeBuilder()->getRootNode();

        static::assertInstanceOf(ArrayNodeDefinition::class, $rootNode);
        $nodes = $rootNode->getChildNodeDefinitions();

        static::assertArrayHasKey('product', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $searchNode = $nodes['product']);

        $nodes = $searchNode->getChildNodeDefinitions();

        static::assertArrayHasKey('allowed_types', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['allowed_types']);

        static::assertArrayHasKey('search_keyword', $nodes);
        static::assertInstanceOf(ArrayNodeDefinition::class, $nodes['search_keyword']);

        $nodes = $nodes['search_keyword']->getChildNodeDefinitions();

        static::assertArrayHasKey('relevant_keyword_count', $nodes);
        static::assertInstanceOf(IntegerNodeDefinition::class, $nodes['relevant_keyword_count']);
    }

    public function testWebhookHealthDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['webhook' => ['health' => []]],
        ]);

        static::assertSame(
            [300, 600, 1200, 2400, 3600, 14400],
            $config['webhook']['health']['cooldown_schedule_seconds']
        );
        static::assertSame(5, $config['webhook']['health']['degraded_threshold_count']);
        static::assertSame(3, $config['webhook']['health']['non_transient_threshold_count']);
        static::assertSame(7, $config['webhook']['health']['max_suspended_days']);
    }

    /**
     * @param array<string, mixed> $healthConfig
     */
    #[DataProvider('invalidWebhookHealthConfigProvider')]
    public function testRejectsInvalidWebhookHealthConfig(array $healthConfig): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            ['webhook' => ['health' => $healthConfig]],
        ]);
    }

    public function testWebhookHealthCooldownScheduleReplacesPreviousConfiguration(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['webhook' => ['health' => ['cooldown_schedule_seconds' => [300, 600]]]],
            ['webhook' => ['health' => [
                'cooldown_schedule_seconds' => [60],
                'degraded_threshold_count' => 1,
            ]]],
        ]);

        static::assertSame([60], $config['webhook']['health']['cooldown_schedule_seconds']);
        static::assertSame(1, $config['webhook']['health']['degraded_threshold_count']);
    }

    public function testWebhookDoesNotAcceptNetworkPolicy(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            [
                'webhook' => [
                    'allow_unencrypted_traffic' => true,
                ],
            ],
        ]);
    }

    public function testAppSystemNetworkPolicyDefaultsAreSecure(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        static::assertTrue($config['app_system']['enable_url_validation']);
        static::assertFalse($config['app_system']['allow_unencrypted_traffic']);
        static::assertSame([], $config['app_system']['allowed_private_ip_addresses']);
    }

    public function testAppSystemNetworkPolicyCanBeConfigured(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            [
                'app_system' => [
                    'enable_url_validation' => false,
                    'allow_unencrypted_traffic' => true,
                    'allowed_private_ip_addresses' => ['10.0.0.10', 'fd00::1'],
                ],
            ],
        ]);

        static::assertFalse($config['app_system']['enable_url_validation']);
        static::assertTrue($config['app_system']['allow_unencrypted_traffic']);
        static::assertSame(['10.0.0.10', 'fd00::1'], $config['app_system']['allowed_private_ip_addresses']);
    }

    public function testAppSystemNetworkPolicyRejectsInvalidAllowedIpAddress(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('Invalid configuration for path "shopware.app_system.allowed_private_ip_addresses.0": ""not-an-ip"" is not a valid IP address.'));

        (new Processor())->processConfiguration(new Configuration(), [
            [
                'app_system' => [
                    'allowed_private_ip_addresses' => ['not-an-ip'],
                ],
            ],
        ]);
    }

    public function testFilesystemVisibilityOverrideKeepsConfiguredAdapter(): void
    {
        $configuration = new Configuration();

        $config = (new Processor())->processConfiguration($configuration, [
            [
                'filesystem' => [
                    'public' => [
                        'type' => 'local',
                        'visibility' => 'public',
                        'config' => [
                            'root' => '%kernel.project_dir%/public',
                        ],
                    ],
                ],
            ],
            [
                'filesystem' => [
                    'public' => [
                        'visibility' => 'private',
                    ],
                ],
            ],
        ]);

        static::assertSame('local', $config['filesystem']['public']['type']);
        static::assertSame('private', $config['filesystem']['public']['visibility']);
        static::assertSame(['root' => '%kernel.project_dir%/public'], $config['filesystem']['public']['config']);
    }

    public function testRemoteThumbnailFallbackSizesDefaultToEmptyList(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            [
                'media' => [
                    'remote_thumbnails' => [],
                ],
            ],
        ]);

        static::assertSame([], $config['media']['remote_thumbnails']['fallback_sizes']);
    }

    public function testRemoteThumbnailFallbackSizesRetainsValidEntry(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            [
                'media' => [
                    'remote_thumbnails' => [
                        'fallback_sizes' => [
                            ['width' => 100, 'height' => 200],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame([['width' => 100, 'height' => 200]], $config['media']['remote_thumbnails']['fallback_sizes']);
    }

    public function testRemoteThumbnailFallbackSizesReplacesPreviousList(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            [
                'media' => [
                    'remote_thumbnails' => [
                        'fallback_sizes' => [
                            ['width' => 100, 'height' => 200],
                        ],
                    ],
                ],
            ],
            [
                'media' => [
                    'remote_thumbnails' => [
                        'fallback_sizes' => [
                            ['width' => 300, 'height' => 400],
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame([['width' => 300, 'height' => 400]], $config['media']['remote_thumbnails']['fallback_sizes']);
    }

    /**
     * @param array{width: int, height: int} $fallbackSize
     */
    #[DataProvider('invalidRemoteThumbnailFallbackSizes')]
    public function testRemoteThumbnailFallbackSizesRejectNonPositiveDimensions(array $fallbackSize, string $path, int $value): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException(
            \sprintf('The value %d is too small for path "%s". Should be greater than or equal to 1', $value, $path)
        ));

        (new Processor())->processConfiguration(new Configuration(), [
            [
                'media' => [
                    'remote_thumbnails' => [
                        'fallback_sizes' => [$fallbackSize],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return \Generator<string, array{fallbackSize: array{width: int, height: int}, path: string, value: int}>
     */
    public static function invalidRemoteThumbnailFallbackSizes(): \Generator
    {
        yield 'zero width' => [
            'fallbackSize' => ['width' => 0, 'height' => 100],
            'path' => 'shopware.media.remote_thumbnails.fallback_sizes.0.width',
            'value' => 0,
        ];

        yield 'negative height' => [
            'fallbackSize' => ['width' => 100, 'height' => -1],
            'path' => 'shopware.media.remote_thumbnails.fallback_sizes.0.height',
            'value' => -1,
        ];
    }

    public function testFilesystemAdapterConfigOverrideReplacesPreviousAdapterConfig(): void
    {
        $configuration = new Configuration();

        $config = (new Processor())->processConfiguration($configuration, [
            [
                'filesystem' => [
                    'public' => [
                        'type' => 'local',
                        'visibility' => 'public',
                        'config' => [
                            'root' => '%kernel.project_dir%/public',
                        ],
                    ],
                ],
            ],
            [
                'filesystem' => [
                    'public' => [
                        'type' => 'amazon-s3',
                        'config' => [
                            'bucket' => 'test',
                            'region' => 'eu-central-1',
                        ],
                    ],
                ],
            ],
        ]);

        static::assertSame('amazon-s3', $config['filesystem']['public']['type']);
        static::assertSame('public', $config['filesystem']['public']['visibility']);
        static::assertSame(['bucket' => 'test', 'region' => 'eu-central-1'], $config['filesystem']['public']['config']);
    }

    public function testInheritingFilesystemNullOverrideRemovesPreviousConfig(): void
    {
        $configuration = new Configuration();

        $config = (new Processor())->processConfiguration($configuration, [
            [
                'filesystem' => [
                    'public' => $this->createS3FilesystemConfig('public'),
                    'theme' => $this->createS3FilesystemConfig('theme'),
                    'asset' => $this->createS3FilesystemConfig('asset'),
                    'sitemap' => $this->createS3FilesystemConfig('sitemap'),
                ],
            ],
            [
                'filesystem' => [
                    'public' => [
                        'type' => 'local',
                        'config' => [
                            'root' => '%kernel.project_dir%/public',
                        ],
                    ],
                    'theme' => null,
                    'asset' => null,
                    'sitemap' => null,
                ],
            ],
        ]);

        static::assertSame('local', $config['filesystem']['public']['type']);
        static::assertSame(['root' => '%kernel.project_dir%/public'], $config['filesystem']['public']['config']);

        static::assertArrayNotHasKey('theme', $config['filesystem']);
        static::assertArrayNotHasKey('asset', $config['filesystem']);
        static::assertArrayNotHasKey('sitemap', $config['filesystem']);
    }

    public function testValidSystemConfigKeys(): void
    {
        $configuration = new Configuration();
        $salesChannelId = Uuid::randomHex();

        $systemConfigs = (new Processor())->processConfiguration($configuration, [
            'shopware' => [
                'system_config' => [
                    'default' => [
                        'core.listing.allowBuyInListing' => true,
                    ],
                    $salesChannelId => [
                        'core.listing.allowBuyInListing' => false,
                    ],
                ],
            ],
        ]);

        static::assertTrue($systemConfigs['system_config']['default']['core.listing.allowBuyInListing']);
        static::assertFalse($systemConfigs['system_config'][$salesChannelId]['core.listing.allowBuyInListing']);
    }

    public function testInvalidSystemConfigKeys(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('Invalid configuration for path "shopware.system_config": Key must be "default" or a valid UUID'));

        $configuration = new Configuration();

        (new Processor())->processConfiguration($configuration, [
            'shopware' => [
                'system_config' => [
                    'default' => [
                        'core.listing.allowBuyInListing' => true,
                    ],
                    'foobar' => [
                        'core.listing.allowBuyInListing' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidWebhookHealthConfigProvider(): iterable
    {
        yield 'empty cooldown schedule' => [['cooldown_schedule_seconds' => []]];
        yield 'first cooldown does not exceed delivery timeout' => [['cooldown_schedule_seconds' => [20]]];
        yield 'non-positive cooldown' => [['cooldown_schedule_seconds' => [300, 0]]];
        yield 'non-positive degraded threshold' => [['degraded_threshold_count' => 0]];
        yield 'non-positive non-transient threshold' => [['non_transient_threshold_count' => 0]];
        yield 'non-positive suspension bound' => [['max_suspended_days' => 0]];
        yield 'suspension bound above maximum' => [['max_suspended_days' => 15]];
    }

    /**
     * @return array{type: string, url: string, visibility: string, config: array{bucket: string, region: string, root: string, endpoint: string, use_path_style_endpoint: bool}}
     */
    private function createS3FilesystemConfig(string $bucket): array
    {
        return [
            'type' => 'amazon-s3',
            'url' => 'https://cdn.example.test/' . $bucket,
            'visibility' => 'private',
            'config' => [
                'bucket' => $bucket,
                'region' => 'eu-central-1',
                'root' => 'asdf',
                'endpoint' => 'localhost/public',
                'use_path_style_endpoint' => true,
            ],
        ];
    }
}
