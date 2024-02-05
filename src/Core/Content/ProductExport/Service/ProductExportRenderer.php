<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Monolog\Level;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ProductExportRenderer implements ProductExportRendererInterface
{
    /**
     * @param array<mixed> $fileSystemConfig
     *
     * @internal
     */
    public function __construct(
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly array $fileSystemConfig
    ) {
    }

    public function renderHeader(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExport->getHeaderTemplate() === null) {
            return '';
        }

        $headerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderHeaderContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            $content = $this->templateRenderer->render(
                $productExport->getHeaderTemplate(),
                $headerContext->getContext(),
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderHeaderException = ProductExportException::renderHeaderException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderHeaderException);

            throw $renderHeaderException;
        }
    }

    public function renderFooter(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExport->getFooterTemplate() === null) {
            return '';
        }

        $footerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderFooterContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            $content = $this->templateRenderer->render(
                $productExport->getFooterTemplate(),
                $footerContext->getContext(),
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderFooterException = ProductExportException::renderFooterException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderFooterException);

            throw $renderFooterException;
        }
    }

    /**
     * @param array<string, mixed>               $data
     */
    public function renderBody(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $data
    ): string {
        $bodyTemplate = $productExport->getBodyTemplate();
        if (!\is_string($bodyTemplate)) {
            throw ProductExportException::templateBodyNotSet();
        }

        try {
            $content = $this->templateRenderer->render(
                $bodyTemplate,
                $data,
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            $content = $this->replaceSalesChannelDomainUrl($content, $productExport->getSalesChannelDomain()->getUrl());

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderProductException = ProductExportException::renderProductException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderProductException);

            throw $renderProductException;
        }
    }

    private function logException(
        Context $context,
        \Exception $exception
    ): void {
        $loggingEvent = new ProductExportLoggingEvent(
            $context,
            $exception->getMessage(),
            Level::Warning,
            $exception
        );

        $this->eventDispatcher->dispatch($loggingEvent);
    }

    private function replaceSeoUrlPlaceholder(
        string $content,
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string {
        return $this->seoUrlPlaceholderHandler->replace(
            $content,
            $productExportEntity->getSalesChannelDomain()->getUrl(),
            $salesChannelContext
        );
    }

    private function replaceSalesChannelDomainUrl(
        string $content,
        string $salesChannelDomainUrl
    ): string {
        $defaultUrl = $this->fileSystemConfig['url'] ?? $this->getFallbackUrl();
        $defaultUrl = rtrim($defaultUrl, '/');

        $salesChannelDomainUrl = rtrim($salesChannelDomainUrl, '/');

        if ($defaultUrl === $salesChannelDomainUrl) {
            return $content;
        }

        return str_replace($defaultUrl, $salesChannelDomainUrl, $content);
    }

    private function getFallbackUrl(): string
    {
        $request = Request::createFromGlobals();
        $requestUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();

        if ($request->getHost() === '' && EnvironmentHelper::getVariable('APP_URL')) {
            $requestUrl = (string) EnvironmentHelper::getVariable('APP_URL');
        }

        return $requestUrl;
    }
}
