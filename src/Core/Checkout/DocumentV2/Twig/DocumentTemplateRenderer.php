<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Twig;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Twig\Environment;

/**
 * Renders a document Twig template under the order's locale, translator and sales-channel context.
 *
 * Auto-injects `order`, `documentNumber`, `rootDir` and `context` into the template scope.
 * Renderers pass only the view path, the {@see RenderInput} and any format-specific extras
 * (e.g. an HTML pagination counter or PDF configuration).
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentTemplateRenderer
{
    public function __construct(
        private TemplateFinder $templateFinder,
        private Environment $twig,
        private AbstractTranslator $translator,
        private AbstractSalesChannelContextFactory $contextFactory,
        private string $rootDir,
    ) {
    }

    /**
     * @param array<string, mixed> $additionalParameters
     *
     * @throws DocumentV2Exception
     */
    public function render(
        string $view,
        RenderInput $input,
        Context $context,
        array $additionalParameters = [],
    ): string {
        $salesChannelId = $input->order->getSalesChannelId();
        $languageId = $input->order->getLanguageId();
        $locale = $input->order->getLanguage()?->getLocale()?->getCode() ?? '';

        $salesChannelContext = $this->contextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            [SalesChannelContextService::LANGUAGE_ID => $languageId],
        );

        $parameters = [
            'order' => $input->order,
            'documentNumber' => $input->documentNumber,
            'rootDir' => $this->rootDir,
            'context' => $salesChannelContext,
            ...$additionalParameters,
        ];

        $this->translator->injectSettings(
            $salesChannelId,
            $languageId,
            $locale,
            $context,
        );

        try {
            $this->templateFinder->reset();

            return $this->twig->render(
                $this->templateFinder->find($view),
                $parameters
            );
        } catch (\Throwable $exception) {
            throw DocumentV2Exception::templateRenderFailed($view, $exception);
        } finally {
            $this->translator->resetInjection();
        }
    }
}
