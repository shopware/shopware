<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Twig\Error\Error;

class InvoiceGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@Shopware/documents/invoice.html.twig';

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
        return DocumentTypes::INVOICE;
    }

    public function documentConfiguration(): DocumentConfiguration
    {
        return new DocumentConfiguration();
    }

    /**
     * @throws Error
     */
    public function generateFromTemplate(
        OrderEntity $order,
        DocumentConfiguration $config,
        Context $context,
        ?string $templatePath = null
    ): string {
        $templatePath = $templatePath ?? self::DEFAULT_TEMPLATE;

        return $this->twigEngine->render($templatePath, [
            'order' => $order,
            'config' => DocumentConfigurationFactory::mergeConfiguration($config, $this->documentConfiguration())->toArray(),
            'rootDir' => $this->rootDir,
            'context' => $context,
        ]);
    }
}
