<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversNothing]
class SalesChannelTranslatedNameTest extends TestCase
{
    public const SALES_CHANNEL_NAME_SEARCH_REGEX = '/^.*(salesChannel.name)+.*$/m';

    public function testMailTemplateContentCollectionFile(): void
    {
        $fileSystem = new Filesystem();
        $mailTemplateCollectionFileContent = $fileSystem->readFile(
            __DIR__ . '/../../src/Core/Migration/Fixtures/MailTemplateContent.php'
        );

        static::assertStringNotContainsString(
            '{{ salesChannel.name }}',
            $mailTemplateCollectionFileContent,
            'Do not use the twig tag "{{ salesChannel.name }}" for mail templates and it translations. Use "{{ salesChannel.translated.name }}" instead.'
        );
    }

    public function testMailTemplateFiles(): void
    {
        $baseDirectory = realpath(__DIR__ . '/../../src/Core/Migration/Fixtures/mails');
        static::assertIsString($baseDirectory);

        $result = [];
        $files = $this->findFilesWithTwigTag($baseDirectory);

        foreach ($files as $file) {
            static::assertInstanceOf(SplFileInfo::class, $file);
            $result[] = $file->getRealPath();
        }

        $message = 'Do not use the twig tag "{{ salesChannel.name }}" for mail templates and it translations. Use "{{ salesChannel.translated.name }}" instead.';
        foreach ($result as $file) {
            $message .= \PHP_EOL . $file;
        }

        static::assertCount(0, $result, $message);
    }

    public function testMigrationFiles(): void
    {
        $fileSystem = new Filesystem();

        $baseDirectory = realpath(__DIR__ . '/../../src/Core/Migration');
        static::assertIsString($baseDirectory);
        static::assertDirectoryExists($baseDirectory);

        $result = \json_decode(
            $fileSystem->readFile(__DIR__ . '/SalesChannelTranslatedNameResult.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $fileList = [];
        $files = $this->findFilesWithTwigTag($baseDirectory);

        foreach ($files as $file) {
            static::assertInstanceOf(SplFileInfo::class, $file);
            $fileList[] = $file->getFilename();
        }

        $fileList = \array_values(array_unique($fileList));
        // to ensure find the allowed 22 files. (Old migrations)
        static::assertGreaterThanOrEqual(22, \count($fileList));

        foreach ($fileList as $fileName) {
            $index = array_search($fileName, $result, true);
            if ($index !== false) {
                unset($result[$index]);
                continue;
            }

            $result[] = $fileName;
        }

        $message = 'Do not use the twig tag "{{ salesChannel.name }}" for mail templates and it translations. Use "{{ salesChannel.translated.name }}" instead.';
        foreach ($result as $file) {
            $message .= \PHP_EOL . $file;
        }

        static::assertCount(0, $result, $message);
    }

    /**
     * @return \Iterator<non-empty-string, SplFileInfo>
     */
    private function findFilesWithTwigTag(string $baseDirectory): \Iterator
    {
        return (new Finder())
            ->files()
            ->depth('1')
            ->in($baseDirectory)
            ->contains(self::SALES_CHANNEL_NAME_SEARCH_REGEX)
            ->sortByCaseInsensitiveName()
            ->getIterator();
    }
}
