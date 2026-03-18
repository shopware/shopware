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

    public function testMigrationFiles(): void
    {
        $fileSystem = new Filesystem();
        $finder = new Finder();

        $baseDirectory = realpath(__DIR__ . '/../../src/Core/Migration');
        static::assertDirectoryExists($baseDirectory);

        $migrationDirectories = [
            $baseDirectory . '/V6_3',
            $baseDirectory . '/V6_4',
            $baseDirectory . '/V6_5',
            $baseDirectory . '/V6_6',
            $baseDirectory . '/V6_7',
            $baseDirectory . '/V6_8',
            $baseDirectory . '/V6_9',
            $baseDirectory . '/V6_10',
        ];

        $result = \json_decode(
            $fileSystem->readFile(__DIR__ . '/SalesChannelTranslatedNameResult.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $fileList = [];
        foreach ($migrationDirectories as $migrationDirectory) {
            if (!$fileSystem->exists($migrationDirectory)) {
                continue;
            }

            $files = $finder
                ->files()
                ->in($migrationDirectory)
                ->contains('/^.*(\{\{ salesChannel.name \}\})+.*$/m')
                ->sortByCaseInsensitiveName()
                ->getIterator();

            foreach ($files as $file) {
                $fileList[] = $file->getFilename();
            }
        }

        $fileList = \array_values(array_unique($fileList));
        // to ensure find the allowed 22 files
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
