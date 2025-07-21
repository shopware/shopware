<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationConfigLoader::class)]
class TranslationConfigLoaderTest extends TestCase
{
    public function testLoadTranslationConfig(): void
    {
        $config = TranslationConfigLoader::load();

        static::assertSame(
            'https://raw.githubusercontent.com/shopware/translations/main/translations',
            $config->repositoryUrl
        );

        $locales = $config->locales;
        static::assertIsArray($locales);
        static::assertContains('en-GB', $locales);

        $plugins = $config->plugins;
        static::assertIsArray($plugins);
        static::assertContains('SwagCommercial', $plugins);

        $languages = $config->languages;
        $language = $languages->get('en-GB');
        static::assertInstanceOf(Language::class, $language);
        static::assertSame('English', $language->name);

        $pluginMapping = $config->pluginMapping;
        static::assertSame('PluginPublisher', $pluginMapping['SwagPublisher']);
    }

    public function testGetMappedPluginName(): void
    {
        $plugin = new TestPlugin(true, 'path/to/plugin');
        $plugin->setName('SwagPublisher');

        $mappedName = TranslationConfigLoader::getMappedPluginName($plugin);
        static::assertSame('PluginPublisher', $mappedName);

        $plugin->setName('SwagCommercial');
        $mappedName = TranslationConfigLoader::getMappedPluginName($plugin);
        static::assertSame('SwagCommercial', $mappedName);
    }
}
