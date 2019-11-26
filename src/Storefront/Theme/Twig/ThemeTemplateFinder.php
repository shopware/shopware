<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Framework\Twig\TemplateFinder;
use Shopware\Core\Kernel;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ThemeTemplateFinder extends TemplateFinder implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(
        Environment $twig,
        FilesystemLoader $loader,
        string $cacheDir,
        RequestStack $requestStack,
        Kernel $kernel
    ) {
        parent::__construct($twig, $loader, $cacheDir);
        $this->requestStack = $requestStack;
        $this->kernel = $kernel;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'registerThemes',
        ];
    }

    public function registerThemes(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $themes = $this->getDetectedThemes($request);

        if (empty($themes)) {
            return;
        }

        $this->bundles = $this->filterBundles($this->bundles, $themes);
    }

    private function filterBundles(array $bundles, array $themes): array
    {
        $filtered = [];

        foreach ($bundles as $bundle) {
            $kernelBundles = $this->kernel->getBundles();
            $bundleClass = null;
            if (array_key_exists($bundle, $kernelBundles)) {
                $bundleClass = $this->kernel->getBundle($bundle);
            }
            if (
                $bundleClass === null

                // add all plugins
                || !($bundleClass instanceof ThemeInterface)

                // always add storefront for new routes and templates fallback
                || $bundle === StorefrontPluginRegistry::BASE_THEME_NAME

                // filter all none active themes
                || isset($themes[$bundle])
            ) {
                $filtered[] = $bundle;
            }
        }

        return $filtered;
    }

    private function getDetectedThemes(Request $request): array
    {
        // detect active themes of request
        $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME);
        if (!$theme) {
            return [];
        }

        $themes = [
            $theme => true,
        ];

        $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        if ($theme) {
            $themes[$theme] = true;
        }

        return $themes;
    }
}
