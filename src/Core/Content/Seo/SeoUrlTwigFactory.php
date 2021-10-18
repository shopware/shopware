<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\SlugifyInterface;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;

class SeoUrlTwigFactory
{
    public function createTwigEnvironment(SlugifyInterface $slugify, iterable $twigExtensions = []): Environment
    {
        $twig = new Environment(new ArrayLoader());
        $twig->setCache(false);
        $twig->enableStrictVariables();
        $twig->addExtension(new SlugifyExtension($slugify));

        /** @var EscaperExtension $coreExtension */
        $coreExtension = $twig->getExtension(EscaperExtension::class);
        $coreExtension->setEscaper(
            SeoUrlGenerator::ESCAPE_SLUGIFY,
            // Do not remove $_twig, although it is marked as unused. It somehow important
            static function ($_twig, $string) use ($slugify) {
                return rawurlencode($slugify->slugify($string));
            }
        );

        foreach ($twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        return $twig;
    }
}
