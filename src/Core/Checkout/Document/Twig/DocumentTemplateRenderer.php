<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Twig;

use Shopware\Core\Checkout\Document\DocumentGenerator\Counter;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
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
     * @var AbstractSalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        TemplateFinder $templateFinder,
        Environment $twig,
        Translator $translator,
        AbstractSalesChannelContextFactory $contextFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->templateFinder = $templateFinder;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->contextFactory = $contextFactory;
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
            $salesChannelContext = $this->contextFactory->create(
                Uuid::randomHex(),
                $salesChannelId,
                [SalesChannelContextService::LANGUAGE_ID => $languageId]
            );
            $salesChannelContext->addState(DocumentService::GENERATING_PDF_STATE);
            $parameters['context'] = $salesChannelContext;
        }

        $documentTemplateRendererParameterEvent = new DocumentTemplateRendererParameterEvent($parameters);
        $this->eventDispatcher->dispatch($documentTemplateRendererParameterEvent);
        $parameters['extensions'] = $documentTemplateRendererParameterEvent->getExtensions();

        $parameters['counter'] = new Counter();
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
