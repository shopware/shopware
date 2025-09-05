<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\UpdateTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateTranslationCommand::class)]
class UpdateTranslationCommandTest extends TestCase
{
    private TranslationLoader&MockObject $translationLoader;

    private TranslationMetadataLoader&MockObject $metadataLoader;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
        $this->metadataLoader = $this->createMock(TranslationMetadataLoader::class);
    }

    public function testExecuteUpdatesAllInstalledTranslations(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $de = MetadataEntry::create([
            'locale' => 'de-DE',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $gb = MetadataEntry::create([
            'locale' => 'en-GB',
            'updatedAt' => '2024-01-01T00:00:00+00:00',
            'progress' => 100,
        ]);

        $metadataCollection = new MetadataCollection([$de, $gb]);
        $metadataCollection->get('de-DE')?->markForUpdate();
        $metadataCollection->get('en-GB')?->markForUpdate();

        $this->initMetadataLoader($metadataCollection);

        $this->translationLoader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(function (string $locale): void {
                $expectedLocales = ['de-DE', 'en-GB'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
            });

        $this->metadataLoader->expects($this->once())
            ->method('save')
            ->with($metadataCollection);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $output = $tester->getDisplay();
        static::assertStringContainsString('1/2 -- Fetching translations for locale: de-DE', $output);
        static::assertStringContainsString('2/2 -- Fetching translations for locale: en-GB', $output);
        static::assertStringContainsString('Saving translation metadata...', $output);
        static::assertStringContainsString('Translation metadata saved successfully.', $output);
    }

    private function getCommand(): UpdateTranslationCommand
    {
        return new UpdateTranslationCommand($this->translationLoader, $this->metadataLoader);
    }

    private function initMetadataLoader(MetadataCollection $collection): void
    {
        $this->metadataLoader->expects($this->once())
            ->method('getUpdatedLocalMetadata')
            ->willReturn($collection);
    }
}
