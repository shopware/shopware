<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationConfigLoaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testContainerExposesTranslationConfigParameterWithDefaults(): void
    {
        static::assertSame([
            'use_local_filesystem' => false,
            'scheduled_task' => [
                'enabled' => true,
            ],
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
        ], static::getContainer()->getParameter('shopware.translation'));
    }

    public function testTranslationConfigServiceIsBuiltFromShippedDefaults(): void
    {
        $config = static::getContainer()->get(TranslationConfig::class);

        static::assertInstanceOf(TranslationConfig::class, $config);
        static::assertSame(
            'https://raw.githubusercontent.com/shopware/translations/main/translations',
            $config->repositoryUrl->__toString()
        );
        static::assertSame(['de-DE', 'en-GB'], $config->excludedLocales);
    }

    public function testConfigOverridesApplyAgainstShippedDefaults(): void
    {
        $loader = new TranslationConfigLoader(new Filesystem(), [
            'repository_url' => 'https://integration.example.com/repo',
            'excluded_locales' => [],
            'plugins' => ['IntegrationPlugin'],
        ]);

        $config = $loader->load();

        // overridden options take effect
        static::assertSame('https://integration.example.com/repo', $config->repositoryUrl->__toString());
        static::assertSame([], $config->excludedLocales);
        static::assertSame(['IntegrationPlugin'], $config->plugins);

        // options left unset fall back to the shipped translation.yaml
        static::assertSame(
            'https://raw.githubusercontent.com/shopware/translations/main/crowdin-metadata.json',
            $config->metadataUrl->__toString()
        );
        static::assertNotNull($config->languages->get('af-ZA'));
    }
}
