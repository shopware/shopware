<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Twig\Error\Error;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use InvoiceRenderer instead
 */
class InvoiceGenerator implements DocumentGeneratorInterface
{
    public const DEFAULT_TEMPLATE = '@Framework/documents/invoice.html.twig';
    public const INVOICE = 'invoice';

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var DocumentTemplateRenderer
     */
    private $documentTemplateRenderer;

    /**
     * @internal
     */
    public function __construct(DocumentTemplateRenderer $documentTemplateRenderer, string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->documentTemplateRenderer = $documentTemplateRenderer;
    }

    public function supports(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'InvoiceRenderer::render')
        );

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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'will be removed, use InvoiceRenderer::render instead'
        );

        $templatePath = $templatePath ?? self::DEFAULT_TEMPLATE;

        $config = DocumentConfigurationFactory::mergeConfiguration($config, new DocumentConfiguration())->jsonSerialize();

        $config['intraCommunityDelivery'] = $this->isAllowIntraCommunityDelivery($config, $order);

        /** @var LanguageEntity $language */
        $language = $order->getLanguage();
        /** @var LocaleEntity $locale */
        $locale = $language->getLocale();

        return $this->documentTemplateRenderer->render(
            $templatePath,
            [
                'order' => $order,
                'config' => $config,
                'rootDir' => $this->rootDir,
                'context' => $context,
            ],
            $context,
            $order->getSalesChannelId(),
            $order->getLanguageId(),
            $locale->getCode()
        );
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $config->getFilenamePrefix() . $config->getDocumentNumber() . $config->getFilenameSuffix();
    }

    private function isAllowIntraCommunityDelivery(array $config, OrderEntity $order): bool
    {
        if (empty($config['displayAdditionalNoteDelivery']) || empty($config['deliveryCountries'])) {
            return false;
        }

        $deliveries = $order->getDeliveries();

        if (empty($deliveries)) {
            return false;
        }

        /** @var OrderDeliveryEntity $delivery */
        $delivery = $deliveries->first();

        /** @var OrderAddressEntity $shippingAddress */
        $shippingAddress = $delivery->getShippingOrderAddress();

        $country = $shippingAddress->getCountry();

        if (!$country) {
            return false;
        }

        $isCompanyTaxFree = $country->getCompanyTax()->getEnabled();

        return $isCompanyTaxFree && \in_array($country->getId(), $config['deliveryCountries'], true);
    }
}
