<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class CheckoutGatewayResponse
{
    /**
     * @var string[]
     */
    public array $paymentMethods = [];

    /**
     * @var string[]
     */
    public array $shippingMethods = [];

    /**
     * @var array{reason: string, level: int, blockOrder: bool}[]
     */
    public array $errors = [];

    /**
     * @internal
     *
     * @param array{paymentMethods: string[], shippingMethods: string[], errors: array{reason: string, level: int, blockOrder: bool}[]} $data
     */
    public static function create(array $data): self
    {
        $response = new self();

        if (\array_key_exists('paymentMethods', $data) && \is_array($data['paymentMethods'])) {
            $response->paymentMethods = $data['paymentMethods'];
        }

        if (\array_key_exists('shippingMethods', $data) && \is_array($data['shippingMethods'])) {
            $response->shippingMethods = $data['shippingMethods'];
        }

        if (\array_key_exists('errors', $data) && \is_array($data['errors'])) {
            $response->errors = $data['errors'];
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @return string[]
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }

    /**
     * @return array{reason: string, level: int, blockOrder: bool}[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
