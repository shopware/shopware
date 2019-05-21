<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\TwigBundle\TwigEngine;
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
     * @var TwigEngine
     */
    private $twigEngine;

    public function __construct(TwigEngine $twigEngine, string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->twigEngine = $twigEngine;
    }

    public function supports(): string
    {
        return self::CREDIT_NOTE;
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return $config->getFileNamePrefix() . $config->getDocumentNumber() . $config->getFileNameSuffix();
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
        $creditItems = [];
        if ($lineItems) {
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
                    $creditItems[] = $lineItem;
                }
            }
        }

        return $this->twigEngine->render($templatePath, [
            'order' => $order,
            'creditItems' => $creditItems,
            'config' => DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize(),
            'rootDir' => $this->rootDir,
            'context' => $context,
        ]);
    }
}
