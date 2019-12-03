<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Twig;

use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DocumentTemplateRenderer
{
    /**
     * @var TemplateFinder
     */
    private $templateFinder;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        TemplateFinder $templateFinder,
        Environment $twig,
        Translator $translator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->templateFinder = $templateFinder;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(
        string $view,
        array $parameters = [],
        ?Context $context = null,
        ?string $salesChannelId = null,
        ?string $languageId = null,
        ?string $locale = null
    ): string {
        $view = $this->resolveView($view);

        // If parameters for specific language setting provided, inject to translator
        if ($context !== null && $salesChannelId !== null && $languageId !== null && $locale !== null) {
            $this->translator->injectSettings(
                $salesChannelId,
                $languageId,
                $locale,
                $context
            );
        }

        $documentTemplateRendererParameterEvent = new DocumentTemplateRendererParameterEvent($parameters);
        $this->eventDispatcher->dispatch($documentTemplateRendererParameterEvent);
        $parameters['extensions'] = $documentTemplateRendererParameterEvent->getExtensions();

        $rendered = $this->twig->render($view, $parameters);

        // If injected translator reject it
        if ($context !== null && $salesChannelId !== null && $languageId !== null && $locale !== null) {
            $this->translator->resetInjection();
        }

        return $rendered;
    }

    /**
     * @throws LoaderError
     */
    private function resolveView(string $view): string
    {
        return $this->templateFinder->find($view);
    }
}
