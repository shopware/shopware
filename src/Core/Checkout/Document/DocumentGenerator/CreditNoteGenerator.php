<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Twig\Error\Error;

class CreditNoteGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@Framework/documents/credit_note.html.twig';
    public const CREDIT_NOTE = 'credit_note';

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

    public function supports(): string
    {
        return self::CREDIT_NOTE;
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return $config->getFilenamePrefix() . $config->getDocumentNumber() . $config->getFilenameSuffix();
    }

    /**
     * @throws Error
     */
    public function generate(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string {
        $templatePath = $templatePath ?? self::DEFAULT_TEMPLATE;
        $lineItems = $order->getLineItems();
        $creditItems = new OrderLineItemCollection();

        if ($lineItems) {
            $creditItems = $lineItems->filterByType(LineItem::CREDIT_LINE_ITEM_TYPE);
        }

        foreach ($creditItems as $creditItem) {
            $creditItem->setUnitPrice($creditItem->getUnitPrice() !== 0.0 ? -$creditItem->getUnitPrice() : 0.0);
            $creditItem->setTotalPrice($creditItem->getTotalPrice() !== 0.0 ? -$creditItem->getTotalPrice() : 0.0);
        }

        $creditItemsCalculatedPrice = $creditItems->getPrices()->sum();
        $totalPrice = $creditItemsCalculatedPrice->getTotalPrice();
        $taxAmount = $creditItemsCalculatedPrice->getCalculatedTaxes()->getAmount();
        $taxes = $creditItemsCalculatedPrice->getCalculatedTaxes();

        foreach ($taxes as $tax) {
            $tax->setTax($tax->getTax() !== 0.0 ? -$tax->getTax() : 0.0);
        }

        $price = new CartPrice(
            -($totalPrice - $taxAmount),
            -$totalPrice,
            -$order->getPositionPrice(),
            $taxes,
            $creditItemsCalculatedPrice->getTaxRules(),
            $order->getTaxStatus()
        );

        $order->setLineItems($creditItems);
        $order->setPrice($price);
        $order->setAmountNet($price->getNetPrice());

        return $this->documentTemplateRenderer->render(
            $templatePath,
            [
                'order' => $order,
                'creditItems' => $creditItems,
                'price' => $totalPrice,
                'amountTax' => $taxAmount,
                'config' => DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize(),
                'rootDir' => $this->rootDir,
                'context' => $context,
            ],
            $context,
            $order->getSalesChannelId(),
            $order->getLanguageId(),
            $order->getLanguage()->getLocale()->getCode()
        );
    }
}
