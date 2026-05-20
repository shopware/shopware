<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\TaxProvider;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;

/**
 * @internal
 */
class ManifestFixture extends Manifest
{
    private ?Admin $admin = null;

    private ?Payments $payments = null;

    private ?Tax $tax = null;

    private ?Webhooks $webhooks = null;

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withAdmin(Admin $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

    public function withAdminModule(?Module $module = null): self
    {
        $modules = $this->admin?->getModules() ?? [];
        $modules[] = $module ?? Module::fromArray([
            'name' => 'test-module',
            'label' => ['en-GB' => 'Test module'],
        ]);

        return $this->withAdmin(Admin::fromArray(['modules' => $modules]));
    }

    public function withPayments(Payments $payments): self
    {
        $this->payments = $payments;

        return $this;
    }

    public function withPaymentMethod(?PaymentMethod $paymentMethod = null): self
    {
        $paymentMethods = $this->payments?->getPaymentMethods() ?? [];
        $paymentMethods[] = $paymentMethod ?? PaymentMethod::fromArray([
            'identifier' => 'test-payment-method',
            'name' => ['en-GB' => 'Test payment method'],
        ]);

        return $this->withPayments(Payments::fromArray(['paymentMethods' => $paymentMethods]));
    }

    public function withTax(Tax $tax): self
    {
        $this->tax = $tax;

        return $this;
    }

    public function withTaxProvider(?TaxProvider $taxProvider = null): self
    {
        $taxProviders = $this->tax?->getTaxProviders() ?? [];
        $taxProviders[] = $taxProvider ?? TaxProvider::fromArray([
            'identifier' => 'test-tax-provider',
            'name' => 'Test tax provider',
            'processUrl' => 'https://example.com/provide-taxes',
            'priority' => 1,
        ]);

        return $this->withTax(Tax::fromArray(['taxProviders' => $taxProviders]));
    }

    public function withWebhooks(Webhooks $webhooks): self
    {
        $this->webhooks = $webhooks;

        return $this;
    }

    public function withWebhook(?Webhook $webhook = null): self
    {
        $webhooks = $this->webhooks?->getWebhooks() ?? [];
        $webhooks[] = $webhook ?? Webhook::fromArray([
            'name' => 'test-webhook',
            'url' => 'https://example.com/webhook',
            'event' => 'product.written',
        ]);

        return $this->withWebhooks(Webhooks::fromArray(['webhooks' => $webhooks]));
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function getWebhooks(): ?Webhooks
    {
        return $this->webhooks;
    }

    public function getPayments(): ?Payments
    {
        return $this->payments;
    }

    public function getTax(): ?Tax
    {
        return $this->tax;
    }
}
