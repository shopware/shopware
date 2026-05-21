<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment\DefaultHandler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerDescriptor;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerInterface;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentInstrumentDescriptor;
use Shopware\Core\Framework\Ucp\UcpVersion;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Adapter exposing Shopware's built-in "Invoice" payment method as a UCP
 * payment handler with instrument type `invoice`. No tokenisation is involved
 * because invoice payment carries no credential beyond order-level identity.
 *
 * @internal
 */
#[Package('framework')]
class InvoicePaymentHandler implements UcpPaymentHandlerInterface
{
    public const NAME_ID = 'com.shopware.invoice';
    public const HANDLER_IDENTIFIER = 'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\InvoicePayment';

    public function getNameId(): string
    {
        return self::NAME_ID;
    }

    public function describe(SalesChannelContext $context): UcpPaymentHandlerDescriptor
    {
        return new UcpPaymentHandlerDescriptor(
            id: 'invoice_' . $context->getSalesChannelId(),
            nameId: self::NAME_ID,
            version: UcpVersion::CURRENT,
            specUrl: 'https://developer.shopware.com/ucp/payment-handlers/invoice',
            schemaUrl: 'https://developer.shopware.com/ucp/schemas/invoice-handler.json',
            availableInstruments: [new UcpPaymentInstrumentDescriptor('invoice')],
            config: ['environment' => 'production'],
        );
    }

    public function prepareInstrument(array $instrumentPayload, SalesChannelContext $context): array
    {
        // Invoice has no token; look up the matching Shopware payment method id from the SalesChannelContext
        foreach ($context->getSalesChannel()->getPaymentMethods() ?? [] as $method) {
            if ($method->getHandlerIdentifier() === self::HANDLER_IDENTIFIER) {
                return ['paymentMethodId' => $method->getId(), 'token' => ''];
            }
        }

        // Fallback: assume the sales channel's currently selected method is fine
        return [
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'token' => '',
        ];
    }

    public function supportsTokenisation(): bool
    {
        // Invoice carries no credential — there's nothing to tokenise.
        return false;
    }

    /**
     * Invoice doesn't tokenise — there's no payment credential to redact.
     * Implementations like Stripe/Mollie override this to call out to the PSP.
     */
    public function tokenize(string $type, array $credential, SalesChannelContext $context): ?array
    {
        return null;
    }
}
