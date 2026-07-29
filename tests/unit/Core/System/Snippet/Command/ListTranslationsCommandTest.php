<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\ListTranslationsCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\Language;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ListTranslationsCommand::class)]
class ListTranslationsCommandTest extends TestCase
{
    private TranslationMetadataStore&Stub $metadataStore;

    protected function setUp(): void
    {
        $this->metadataStore = static::createStub(TranslationMetadataStore::class);
    }

    public function testListsConfiguredLocalesSortedWithEnglishNamesAndInstalledMarker(): void
    {
        $languages = new LanguageCollection([
            new Language('es-ES', 'Español'),
            new Language('de-DE', 'Deutsch'),
            new Language('en-GB', 'English'),
        ]);

        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['de-DE', 'en-GB', 'es-ES'],
            [],
            $languages,
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );

        $this->metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection([
            MetadataEntry::create([
                'locale' => 'es-ES',
                'updatedAt' => '2024-06-15T12:34:56+00:00',
                'progress' => 100,
            ]),
        ]));

        $tester = new CommandTester(new ListTranslationsCommand($config, $this->metadataStore));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();

        $dePos = strpos($output, 'de-DE');
        $enPos = strpos($output, 'en-GB');
        $esPos = strpos($output, 'es-ES');

        static::assertNotFalse($dePos);
        static::assertNotFalse($enPos);
        static::assertNotFalse($esPos);
        static::assertLessThan($enPos, $dePos);
        static::assertLessThan($esPos, $enPos);

        static::assertStringContainsString('Deutsch', $output);
        static::assertStringContainsString('English', $output);
        static::assertStringContainsString('Español', $output);
        static::assertStringContainsString('German (Germany)', $output);
        static::assertStringContainsString('Spanish (Spain)', $output);
        static::assertStringContainsString('2024-06-15 12:34', $output);
        static::assertStringContainsString('—', $output);
        static::assertStringContainsString('3 locales configured.', $output);
    }

    public function testRunsSuccessfullyWithEmptyConfiguration(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            [],
            [],
            new LanguageCollection(),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            [],
        );

        $this->metadataStore->method('getLocalMetadata')->willReturn(new MetadataCollection());

        $tester = new CommandTester(new ListTranslationsCommand($config, $this->metadataStore));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('0 locales configured.', $tester->getDisplay());
    }
}
