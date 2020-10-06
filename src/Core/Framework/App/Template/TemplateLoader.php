<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Symfony\Component\Finder\Finder;

class TemplateLoader extends AbstractTemplateLoader
{
    private const ALLOWED_TEMPLATE_DIRS = [
        'storefront',
        'documents',
    ];

    /**
     * {@inheritdoc}
     */
    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewDirectory = $app->getPath() . '/Resources/views';

        if (!\is_dir($viewDirectory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()
            ->in($viewDirectory)
            ->name('*.html.twig')
            ->path(self::ALLOWED_TEMPLATE_DIRS)
            ->ignoreUnreadableDirs();

        return \array_values(\array_map(static function (\SplFileInfo $file) use ($viewDirectory): string {
            // remove viewDirectory + any leading slashes from pathname
            return \ltrim(\mb_substr($file->getPathname(), \mb_strlen($viewDirectory)), '/');
        }, \iterator_to_array($finder)));
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateContent(string $path, Manifest $app): string
    {
        $content = @\file_get_contents($app->getPath() . '/Resources/views/' . $path);

        if ($content === false) {
            throw new \RuntimeException(\sprintf('Unable to read file from: %s.', $app->getPath() . '/views/' . $path));
        }

        return $content;
    }
}
