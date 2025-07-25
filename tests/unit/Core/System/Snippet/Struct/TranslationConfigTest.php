<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\Language;
use Shopware\Core\System\Snippet\Struct\LanguageCollection;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationConfig::class)]
class TranslationConfigTest extends TestCase
{
    public function testTranslationConfig(): void
    {
        $repositoryUrl = 'https://example.com/repository';
        $locales = ['en-GB', 'de-DE'];
        $plugins = ['PluginA', 'PluginB'];
        $languages = new LanguageCollection([
            new Language('en-GB', 'English'),
            new Language('de-DE', 'Deutsch'),
        ]);

        $pluginMapping = [
            'PluginA' => 'plugin-a',
            'PluginB' => 'plugin-b',
        ];

        $config = new TranslationConfig(
            $repositoryUrl,
            $locales,
            $plugins,
            $languages,
            $pluginMapping
        );

        static::assertSame($repositoryUrl, $config->repositoryUrl);
        static::assertSame($locales, $config->locales);
        static::assertSame($plugins, $config->plugins);
        static::assertSame($languages, $config->languages);
        static::assertSame($pluginMapping, $config->pluginMapping);
    }
}
