<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Twig\Error\Error;

class DeliveryNoteGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@Framework/documents/delivery_note.html.twig';
    public const DELIVERY_NOTE = 'delivery_note';

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
        return self::DELIVERY_NOTE;
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

        $deliveries = null;
        if ($order->getDeliveries()) {
            $deliveries = $order->getDeliveries()->first();
        }

        return $this->twigEngine->render($templatePath, [
            'order' => $order,
            'orderDelivery' => $deliveries,
            'config' => DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize(),
            'rootDir' => $this->rootDir,
            'context' => $context,
        ]);
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return $config->getFileNamePrefix() . $config->getDocumentNumber() . $config->getFileNameSuffix();
    }
}
