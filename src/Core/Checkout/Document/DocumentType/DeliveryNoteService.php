<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentType;

use Shopware\Core\Checkout\Document\DocumentContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\TwigBundle\TwigEngine;

class DeliveryNoteService implements DocumentType
{
    public const TYPE = 'delivery_note';

    public const FORMAT = 'pdf';

    public const DEFAULT_TEMPLATE = '@Shopware/documents/delivery_note.html.twig';

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
        return self::TYPE;
    }

    public function documentFormat(): string
    {
        return self::FORMAT;
    }

    public function generateFromTemplate(
        OrderEntity $order,
        DocumentContext $documentContext,
        Context $context,
        string $template = self::DEFAULT_TEMPLATE
    ): string {
        return $this->twigEngine->render($template, [
            'order' => $order,
            'orderDelivery' => $order->getDeliveries()->first(),
            'documentContext' => $documentContext,
            'rootDir' => $this->rootDir,
            'context' => $context,
        ]);
    }
}
