<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class ScriptFileReader implements ScriptFileReaderInterface
{
    private const SCRIPT_DIR = '/Resources/scripts';

    private const ALLOWED_FILE_EXTENSIONS = '*.twig';

    public function __construct(private readonly string $projectDir)
    {
    }

    public function getScriptPathsForApp(string $appPath): array
    {
        $scriptDirectory = $this->projectDir . $appPath . self::SCRIPT_DIR;

        if (!is_dir($scriptDirectory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($scriptDirectory)
            ->exclude('rule-conditions')
            ->name(self::ALLOWED_FILE_EXTENSIONS)
            ->ignoreUnreadableDirs();

        return array_values(array_map(static fn (\SplFileInfo $file): string => ltrim(mb_substr($file->getPathname(), mb_strlen($scriptDirectory)), '/'), iterator_to_array($finder)));
    }

    public function getScriptContent(string $name, string $appPath): string
    {
        $content = @file_get_contents($this->projectDir . $appPath . self::SCRIPT_DIR . '/' . $name);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $this->projectDir . $appPath . self::SCRIPT_DIR . '/' . $name));
        }

        return $content;
    }
}
