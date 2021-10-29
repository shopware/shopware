<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script;

use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
 */
class ScriptLoader implements ScriptLoaderInterface
{
    protected const TEMPLATE_DIR = '/Resources/scripts';

    protected const ALLOWED_TEMPLATE_DIRS = [];

    protected const ALLOWED_FILE_EXTENSIONS = '*.twig';

    /**
     * {@inheritdoc}
     */
    public function getScriptPathsForAppPath(string $appPath): array
    {
        $viewDirectory = $appPath . static::TEMPLATE_DIR;

        if (!is_dir($viewDirectory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($viewDirectory)
            ->name(static::ALLOWED_FILE_EXTENSIONS)
            ->path(static::ALLOWED_TEMPLATE_DIRS)
            ->ignoreUnreadableDirs();

        return array_values(array_map(static function (\SplFileInfo $file) use ($viewDirectory): string {
            // remove viewDirectory + any leading slashes from pathname
            return ltrim(mb_substr($file->getPathname(), mb_strlen($viewDirectory)), '/');
        }, iterator_to_array($finder)));
    }

    public function getScriptContent(string $name, string $appPath): string
    {
        $content = @file_get_contents($appPath . static::TEMPLATE_DIR . '/' . $name);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $appPath . static::TEMPLATE_DIR . '/' . $name));
        }

        return $content;
    }

    public function getLastModifiedDate(string $name, string $appPath): \DateTimeInterface
    {
        $lastModified = @filemtime($appPath . static::TEMPLATE_DIR . '/' . $name);

        if (!$lastModified) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $appPath . static::TEMPLATE_DIR . '/' . $name));
        }

        return (new \DateTimeImmutable())->setTimestamp($lastModified);
    }
}
