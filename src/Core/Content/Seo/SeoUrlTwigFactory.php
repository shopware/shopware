<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * @deprecated tag:v6.7.0 - this class will be internal - reason:becomes-internal
 */
#[Package('buyers-experience')]
class SeoUrlTwigFactory
{
    /**
     * @param ExtensionInterface[] $twigExtensions
     *
     * @deprecated tag:v6.7.0 - the parameter twigExtensions and cacheDir will be required - reason:becomes-internal
     */
    public function createTwigEnvironment(SlugifyInterface $slugify, iterable $twigExtensions = [], ?string $cacheDir = null): Environment
    {
        $twig = new TwigEnvironment(new ArrayLoader());

        if ($cacheDir) {
            $twig->setCache(new FilesystemCache(Path::join($cacheDir, 'twig', 'seo-cache')));
        } else {
            $twig->setCache(false);
        }

        $twig->enableStrictVariables();
        $twig->addExtension(new SlugifyExtension($slugify));
        $twig->addExtension(new PhpSyntaxExtension());
        $twig->addExtension(new SecurityExtension([]));

        foreach ($twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        $twig->getRuntime(EscaperRuntime::class)->setEscaper(
            SeoUrlGenerator::ESCAPE_SLUGIFY,
            static fn ($string) => rawurlencode($slugify->slugify((string) $string))
        );

        return $twig;
    }
}
