<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

/**
 * @deprecated tag:v6.3.0 will be removed, use ThemeNamespaceHierarchyBuilder instead
 */
class ThemeTemplateFinder extends TemplateFinder implements EventSubscriberInterface
{
    /**
     * @var ThemeInheritanceBuilderInterface
     */
    private $inheritanceBuilder;

    public function __construct(
        Environment $twig,
        LoaderInterface $loader,
        string $cacheDir,
        ThemeInheritanceBuilderInterface $inheritanceBuilder,
        ?NamespaceHierarchyBuilder $hierarchyBuilder = null
    ) {
        parent::__construct($twig, $loader, $cacheDir, $hierarchyBuilder);
        $this->inheritanceBuilder = $inheritanceBuilder;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'requestEvent',
        ];
    }

    public function requestEvent(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $themes = $this->detectedThemes($request);

        if (empty($themes)) {
            return;
        }

        $this->bundles = $this->inheritanceBuilder->build($this->bundles, $themes);
    }

    private function detectedThemes(Request $request): array
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
