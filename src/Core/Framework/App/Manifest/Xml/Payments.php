<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Payments extends XmlElement
{
    /**
     * @var PaymentMethod[]
     */
    protected $paymentMethods = [];

    private function __construct(array $paymentMethods)
    {
        $this->paymentMethods = $paymentMethods;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parsePaymentMethods($element));
    }

    /**
     * @return PaymentMethod[]
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

    /**
     * @return PaymentMethod[]
     */
    private static function parsePaymentMethods(\DOMElement $element): array
    {
        $paymentMethods = [];
        foreach ($element->getElementsByTagName('payment-method') as $paymentMethod) {
            $paymentMethods[] = PaymentMethod::fromXml($paymentMethod);
        }

        return $paymentMethods;
    }
}
