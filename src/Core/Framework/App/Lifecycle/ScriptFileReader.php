<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
 */
class ScriptFileReader implements ScriptFileReaderInterface
{
    private const SCRIPT_DIR = '/Resources/scripts';

    private const ALLOWED_FILE_EXTENSIONS = '*.twig';

    public function getScriptPathsForApp(string $appPath): array
    {
        $scriptDirectory = $appPath . static::SCRIPT_DIR;

        if (!is_dir($scriptDirectory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($scriptDirectory)
            ->name(static::ALLOWED_FILE_EXTENSIONS)
            ->ignoreUnreadableDirs();

        return array_values(array_map(static function (\SplFileInfo $file) use ($scriptDirectory): string {
            // remove scriptDirectory + any leading slashes from pathname
            return ltrim(mb_substr($file->getPathname(), mb_strlen($scriptDirectory)), '/');
        }, iterator_to_array($finder)));
    }

    public function getScriptContent(string $name, string $appPath): string
    {
        $content = @file_get_contents($appPath . static::SCRIPT_DIR . '/' . $name);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $appPath . static::SCRIPT_DIR . '/' . $name));
        }

        return $content;
    }
}
