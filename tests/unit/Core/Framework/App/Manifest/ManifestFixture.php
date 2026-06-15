<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\Gateways;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleCondition;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleConditions;
use Shopware\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\Storefront;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\TaxProvider;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;

/**
 * @internal
 */
class ManifestFixture extends Manifest
{
    private Metadata $metadata;

    private ?Admin $admin = null;

    private ?Payments $payments = null;

    private ?RuleConditions $ruleConditions = null;

    private ?Setup $setup = null;

    private ?Tax $tax = null;

    private ?Webhooks $webhooks = null;

    private function __construct()
    {
        $this->metadata = self::createMetadata('test');
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withName(string $name): self
    {
        $this->metadata = self::createMetadata($name);

        return $this;
    }

    public function withAdminModule(?Module $module = null): self
    {
        $modules = $this->admin?->getModules() ?? [];
        $modules[] = $module ?? Module::fromArray([
            'name' => 'test-module',
            'label' => ['en-GB' => 'Test module'],
        ]);

        $this->admin = Admin::fromArray(['modules' => $modules]);

        return $this;
    }

    public function withPaymentMethod(?PaymentMethod $paymentMethod = null): self
    {
        $paymentMethods = $this->payments?->getPaymentMethods() ?? [];
        $paymentMethods[] = $paymentMethod ?? PaymentMethod::fromArray([
            'identifier' => 'test-payment-method',
            'name' => ['en-GB' => 'Test payment method'],
        ]);

        $this->payments = Payments::fromArray(['paymentMethods' => $paymentMethods]);

        return $this;
    }

    /**
     * @param list<CustomFieldType> $constraints
     */
    public function withRuleCondition(
        string $identifier,
        array $constraints = [],
        string $script = 'mock.twig',
        string $group = 'misc'
    ): self {
        $ruleConditions = $this->ruleConditions?->getRuleConditions() ?? [];
        $ruleConditions[] = RuleCondition::fromArray([
            'identifier' => $identifier,
            'name' => ['en-GB' => $identifier],
            'group' => $group,
            'script' => $script,
            'constraints' => $constraints,
        ]);

        $this->ruleConditions = RuleConditions::fromArray(['ruleConditions' => $ruleConditions]);

        return $this;
    }

    public function withSetup(?Setup $setup = null): self
    {
        $this->setup = $setup ?? Setup::fromArray([
            'registrationUrl' => 'https://example.com/register',
        ]);

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

        $this->tax = Tax::fromArray(['taxProviders' => $taxProviders]);

        return $this;
    }

    public function withWebhook(?Webhook $webhook = null): self
    {
        return $this->withWebhooks($webhook ?? Webhook::fromArray([
            'name' => 'test-webhook',
            'url' => 'https://example.com/webhook',
            'event' => 'product.written',
        ]));
    }

    public function withWebhooks(Webhook ...$webhooks): self
    {
        $existingWebhooks = $this->webhooks?->getWebhooks() ?? [];
        $webhooks = [
            ...$existingWebhooks,
            ...$webhooks,
        ];
        $this->webhooks = Webhooks::fromArray(['webhooks' => $webhooks]);

        return $this;
    }

    public function getPath(): string
    {
        return 'test';
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function validatesPermissions(): bool
    {
        return false;
    }

    public function getSetup(): ?Setup
    {
        return $this->setup;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function getPermissions(): ?Permissions
    {
        return null;
    }

    public function getWebhooks(): ?Webhooks
    {
        return $this->webhooks;
    }

    public function getCookies(): ?Cookies
    {
        return null;
    }

    public function getPayments(): ?Payments
    {
        return $this->payments;
    }

    public function getRuleConditions(): ?RuleConditions
    {
        return $this->ruleConditions;
    }

    public function getStorefront(): ?Storefront
    {
        return null;
    }

    public function getTax(): ?Tax
    {
        return $this->tax;
    }

    public function getGateways(): ?Gateways
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getAllHosts(): array
    {
        return [];
    }

    private static function createMetadata(string $name): Metadata
    {
        return Metadata::fromArray([
            'label' => ['en-GB' => $name],
            'name' => $name,
            'author' => 'shopware AG',
            'copyright' => '(c) by shopware AG',
            'license' => 'MIT',
            'version' => '1.0.0',
        ]);
    }
}
