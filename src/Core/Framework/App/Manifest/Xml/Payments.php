<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

/**
 * @internal only for use by the app-system
 */
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
