<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Service;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationFileMap;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationFileMap::class)]
class TranslationFileMapTest extends TestCase
{
    public function testForLocaleMapsTheRepositoryPathsOfPlatformAndPluginBundlesToTheLocaleDirectory(): void
    {
        $config = new TranslationConfig(
            new Uri('https://example.com/translations'),
            ['es-ES'],
            ['SwagPublisher'],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('https://example.com/metadata.json'),
            [],
        );

        static::assertSame([
            'es-ES/Platform/Administration/administration.json' => '/translation/locale/es-ES/Platform/administration.json',
            'es-ES/Platform/Core/messages.json' => '/translation/locale/es-ES/Platform/messages.es-ES.base.json',
            'es-ES/Platform/Storefront/storefront.json' => '/translation/locale/es-ES/Platform/storefront.json',
            'es-ES/Plugins/SwagPublisher/Storefront/storefront.json' => '/translation/locale/es-ES/Plugins/SwagPublisher/storefront.json',
            'es-ES/Plugins/SwagPublisher/Administration/administration.json' => '/translation/locale/es-ES/Plugins/SwagPublisher/administration.json',
        ], TranslationFileMap::forLocale($config, 'es-ES', '/translation/locale/es-ES'));
    }
}
