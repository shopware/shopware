<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;

#[Package('sales-channel')]
class SeoUrlTwigFactory
{
    /**
     * @param ExtensionInterface[] $twigExtensions
     */
    public function createTwigEnvironment(SlugifyInterface $slugify, iterable $twigExtensions = []): Environment
    {
        $twig = new TwigEnvironment(new ArrayLoader());
        $twig->setCache(false);
        $twig->enableStrictVariables();
        $twig->addExtension(new SlugifyExtension($slugify));
        $twig->addExtension(new PhpSyntaxExtension());
        $twig->addExtension(new SecurityExtension([]));

        /** @var EscaperExtension $coreExtension */
        $coreExtension = $twig->getExtension(EscaperExtension::class);
        $coreExtension->setEscaper(
            SeoUrlGenerator::ESCAPE_SLUGIFY,
            // Do not remove $_twig, although it is marked as unused. It somehow important
            static fn ($_twig, $string) => rawurlencode($slugify->slugify($string))
        );

        foreach ($twigExtensions as $twigExtension) {
            $twig->addExtension($twigExtension);
        }

        return $twig;
    }
}
