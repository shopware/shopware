<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Twig;

use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\SalesChannelThemeLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('storefront')]
class ThemeNamespaceHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface, EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<int|string, bool>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeInheritanceBuilderInterface $themeInheritanceBuilder,
        private readonly SalesChannelThemeLoader $salesChannelThemeLoader
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'requestEvent',
            KernelEvents::EXCEPTION => 'requestEvent',
            DocumentTemplateRendererParameterEvent::class => 'onDocumentRendering',
        ];
    }

    public function requestEvent(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $this->themes = $this->detectedThemes($request);
    }

    public function onDocumentRendering(DocumentTemplateRendererParameterEvent $event): void
    {
        $parameters = $event->getParameters();

        if (!\array_key_exists('context', $parameters)) {
            return;
        }

        /** @var SalesChannelContext $context */
        $context = $parameters['context'];

        $themes = [];

        $theme = $this->salesChannelThemeLoader->load($context->getSalesChannelId());

        if (empty($theme['themeName'])) {
            return;
        }

        $themes[$theme['themeName']] = true;
        $themes['Storefront'] = true;

        $this->themes = $themes;
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        if (empty($this->themes)) {
            return $namespaceHierarchy;
        }

        return $this->themeInheritanceBuilder->build($namespaceHierarchy, $this->themes);
    }

    public function reset(): void
    {
        $this->themes = [];
    }

    /**
     * @return array<int|string, bool>
     */
    private function detectedThemes(Request $request): array
    {
        $themes = [];
        // get name if theme is not inherited
        $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME);

        if (!$theme) {
            // get theme name from base theme because for inherited themes the name is always null
            $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!$theme) {
            return [];
        }

        $themes[$theme] = true;
        $themes['Storefront'] = true;

        return $themes;
    }
}
