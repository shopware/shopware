<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class TemplateLoader extends AbstractTemplateLoader
{
    private const TEMPLATE_DIR = '/Resources/views';

    private const ALLOWED_TEMPLATE_DIRS = [
        'storefront',
        'documents',
    ];

    private const ALLOWED_FILE_EXTENSIONS = '*.twig';

    /**
     * {@inheritdoc}
     */
    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewDirectory = $app->getPath() . self::TEMPLATE_DIR;

        if (!is_dir($viewDirectory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($viewDirectory)
            ->name(self::ALLOWED_FILE_EXTENSIONS)
            ->path(self::ALLOWED_TEMPLATE_DIRS)
            ->ignoreUnreadableDirs();

        return array_values(array_map(static fn (\SplFileInfo $file): string => ltrim(mb_substr($file->getPathname(), mb_strlen($viewDirectory)), '/'), iterator_to_array($finder)));
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateContent(string $path, Manifest $app): string
    {
        $content = @file_get_contents($app->getPath() . self::TEMPLATE_DIR . '/' . $path);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $app->getPath() . self::TEMPLATE_DIR . '/' . $path));
        }

        return $content;
    }
}
