<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Twig\Error\Error;

class InvoiceGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@Framework/documents/invoice.html.twig';
    public const INVOICE = 'invoice';

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(Environment $twig, string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->twig = $twig;
    }

    public function supports(): string
    {
        return self::INVOICE;
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

        return $this->twig->render($templatePath, [
            'order' => $order,
            'config' => DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize(),
            'rootDir' => $this->rootDir,
            'context' => $context,
        ]);
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return $config->getFilenamePrefix() . $config->getDocumentNumber() . $config->getFilenameSuffix();
    }
}
