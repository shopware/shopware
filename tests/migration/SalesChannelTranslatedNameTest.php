<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class SalesChannelTranslatedNameTest extends TestCase
{
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
        $finder = new Finder();

        $result = [];
        $files = $finder->files()
            ->depth('1')
            ->in($baseDirectory)
            ->contains('/^.*(\{\{ salesChannel.name \}\})+.*$/m')
            ->sortByCaseInsensitiveName();

        foreach ($files as $file) {
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
        $finder = new Finder();

        $baseDirectory = realpath(__DIR__ . '/../../src/Core/Migration');
        static::assertDirectoryExists($baseDirectory);

        $result = \json_decode(
            $fileSystem->readFile(__DIR__ . '/SalesChannelTranslatedNameResult.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $fileList = [];
        $files = $finder
            ->files()
            ->depth('1')
            ->in($baseDirectory)
            ->contains('/^.*(\{\{ salesChannel.name \}\})+.*$/m')
            ->sortByCaseInsensitiveName()
            ->getIterator();

        foreach ($files as $file) {
            $fileList[] = $file->getFilename();
        }

        $fileList = \array_values(array_unique($fileList));
        // to ensure find the allowed 22 files. (Old migrations)
        static::assertGreaterThan(21, \count($fileList));

        foreach ($fileList as $fileName) {
            $index = array_search($fileName, $result, true);
            if ($index !== false) {
                unset($result[$index]);
                continue;
            }

            $result[] = $file->getFilename();
        }

        $message = 'Do not use the twig tag "{{ salesChannel.name }}" for mail templates and it translations. Use "{{ salesChannel.translated.name }}" instead.';
        foreach ($result as $file) {
            $message .= \PHP_EOL . $file;
        }

        static::assertCount(0, $result, $message);
    }
}
