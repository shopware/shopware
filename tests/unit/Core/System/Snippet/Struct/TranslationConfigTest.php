<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Struct;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationConfig::class)]
class TranslationConfigTest extends TestCase
{
    public function testTranslationConfig(): void
    {
        $repositoryUrl = new Uri('http://localhost:8000');
        $locales = ['en-GB', 'de-DE'];
        $plugins = ['PluginA', 'PluginB'];
        $languages = new LanguageCollection([
            new Language('en-GB', 'English'),
            new Language('de-DE', 'Deutsch'),
        ]);

        $excludedLocales = ['fr-FR', 'es-ES'];

        $pluginMapping = new PluginMappingCollection([
            new PluginMapping('PluginA', 'plugin-a'),
            new PluginMapping('PluginB', 'plugin-b'),
        ]);

        $metadataUrl = new Uri('http://localhost:8000/metadata.json');

        $config = new TranslationConfig(
            $repositoryUrl,
            $locales,
            $plugins,
            $languages,
            $pluginMapping,
            $metadataUrl,
            $excludedLocales,
        );

        static::assertSame($repositoryUrl, $config->repositoryUrl);
        static::assertSame($locales, $config->locales);
        static::assertSame($plugins, $config->plugins);
        static::assertSame($languages, $config->languages);
        static::assertSame($pluginMapping, $config->pluginMapping);
        static::assertSame($metadataUrl, $config->metadataUrl);
        static::assertSame($excludedLocales, $config->excludedLocales);
    }

    public function testGetMappedPluginName(): void
    {
        $pluginWithMapping = new TestPlugin(true, 'path/to/plugin');
        $pluginWithMapping->setName('PluginWithMapping');

        $pluginMapping = new PluginMappingCollection([
            new PluginMapping('PluginWithMapping', 'MappedPluginWithMapping'),
        ]);

        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            [],
            [],
            new LanguageCollection(),
            $pluginMapping,
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );

        $pluginWithoutMapping = new TestPlugin(true, 'path/to/plugin');
        $pluginWithoutMapping->setName('PluginWithoutMapping');

        $mappedName = $config->getMappedPluginName($pluginWithoutMapping);
        static::assertSame('PluginWithoutMapping', $mappedName);

        $mappedName = $config->getMappedPluginName($pluginWithMapping);
        static::assertSame('MappedPluginWithMapping', $mappedName);
    }
}
