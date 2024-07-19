<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ScriptFileReader
{
    private const SCRIPT_DIR = '/Resources/scripts';

    private const ALLOWED_FILE_EXTENSIONS = '*.twig';

    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    /**
     * Returns the list of script paths the given app contains
     *
     * @return array<string>
     */
    public function getScriptPathsForApp(AppEntity $app): array
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has(self::SCRIPT_DIR)) {
            return [];
        }

        $scriptDirectory = $fs->path(self::SCRIPT_DIR);

        $finder = new Finder();
        $finder->files()
            ->in($scriptDirectory)
            ->exclude('rule-conditions')
            ->name(self::ALLOWED_FILE_EXTENSIONS)
            ->ignoreUnreadableDirs();

        return array_values(array_map(static fn (SplFileInfo $file): string => $file->getRelativePathname(), iterator_to_array($finder)));
    }

    /**
     * Returns the content of the script
     */
    public function getScriptContent(AppEntity $app, string $path): string
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        try {
            $content = $fs->read(self::SCRIPT_DIR, $path);
        } catch (\Exception $e) {
            throw new \RuntimeException(\sprintf('Unable to read file from: %s.', $fs->path(self::SCRIPT_DIR, $path)));
        }

        return $content;
    }
}
