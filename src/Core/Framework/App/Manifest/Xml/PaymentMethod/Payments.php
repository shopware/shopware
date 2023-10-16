<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Payments extends XmlElement
{
    /**
     * @var list<PaymentMethod>
     */
    protected array $paymentMethods = [];

    /**
     * @return list<PaymentMethod>
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @return array<string>
     */
    public function getUrls(): array
    {
        $urls = [];

        foreach ($this->paymentMethods as $paymentMethod) {
            $urls[] = $paymentMethod->getCaptureUrl();
            $urls[] = $paymentMethod->getFinalizeUrl();
            $urls[] = $paymentMethod->getValidateUrl();
            $urls[] = $paymentMethod->getPayUrl();
        }

        return array_filter($urls);
    }

    protected static function parse(\DOMElement $element): array
    {
        $paymentMethods = [];
        foreach ($element->getElementsByTagName('payment-method') as $paymentMethod) {
            $paymentMethods[] = PaymentMethod::fromXml($paymentMethod);
        }

        return ['paymentMethods' => $paymentMethods];
    }
}
