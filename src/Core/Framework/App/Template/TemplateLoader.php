<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

/**
 * @internal only for use by the app-system
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

    public function __construct(private readonly AbstractAppLoader $appLoader)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewDirectory = $this->appLoader->locatePath($app->getPath(), self::TEMPLATE_DIR);

        if ($viewDirectory === null) {
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
        $content = $this->appLoader->loadFile($app->getPath(), self::TEMPLATE_DIR . '/' . $path);

        if ($content === null) {
            throw new \RuntimeException(sprintf('Unable to read file from: %s.', $app->getPath() . self::TEMPLATE_DIR . '/' . $path));
        }

        return $content;
    }
}
