<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemInterface;

class SitemapNameGenerator implements SitemapNameGeneratorInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var string
     */
    private $pattern;

    /**
     * @param string $pattern
     */
    public function __construct(FilesystemInterface $filesystem, $pattern = 'sitemap-{number}.xml.gz')
    {
        $this->pattern = $pattern;
        $this->filesystem = $filesystem;
    }

    public function getSitemapFilename(string $sitemapKey): string
    {
        $number = 1;
        do {
            $path = 'sitemap/salesChannel-' . $sitemapKey . '/' . str_ireplace(
                ['{number}'],
                [$number],
                $this->pattern
            );
            ++$number;
        } while ($this->filesystem->has($path));

        return $path;
    }
}
