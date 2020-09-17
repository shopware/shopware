<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Twig\Error\Error;

abstract class AbstractOrderDocumentGenerator
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var DocumentTemplateRenderer
     */
    private $documentTemplateRenderer;

    public function __construct(DocumentTemplateRenderer $documentTemplateRenderer, string $rootDir)
    {
        $this->documentTemplateRenderer = $documentTemplateRenderer;
        $this->rootDir = $rootDir;
    }

    /**
     * @throws Error
     * @throws DocumentGenerationException
     */
    public function generate(
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string {
        $order = $config->order;
        if (!($order instanceof OrderEntity)) {
            throw new DocumentGenerationException();
        }

        $defaultParameters = [
            'config' => $config->jsonSerialize(),
            'context' => $context,
            'order' => $order,
            'rootDir' => $this->rootDir,
        ];
        $parameters = array_merge($defaultParameters, $this->getExtraParameters($order, $context));

        return $this->documentTemplateRenderer->render(
            $templatePath ?? $this->getDefaultTemplate(),
            $parameters,
            $context,
            $order->getSalesChannelId(),
            $order->getLanguageId(),
            $order->getLanguage()->getLocale()->getCode()
        );
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return $config->getFilenamePrefix() . $config->getDocumentNumber() . $config->getFilenameSuffix();
    }

    abstract protected function getExtraParameters(OrderEntity $order, Context $context): array;

    abstract protected function getDefaultTemplate(): string;
}
