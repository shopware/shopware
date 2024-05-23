<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Admin;
use Shopware\Core\Framework\App\Manifest\Xml\AllowedHost\AllowedHosts;
use Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFields;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\Gateways;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleConditions;
use Shopware\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\Storefront;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhooks;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Manifest
{
    private const XSD_FILE = __DIR__ . '/Schema/manifest-2.0.xsd';

    private bool $managedByComposer = false;

    private function __construct(
        private string $path,
        private readonly Metadata $metadata,
        private readonly ?Setup $setup,
        private readonly ?Admin $admin,
        private ?Permissions $permissions,
        private readonly ?AllowedHosts $allowedHosts,
        private readonly ?CustomFields $customFields,
        private readonly ?Webhooks $webhooks,
        private readonly ?Cookies $cookies,
        private readonly ?Payments $payments,
        private readonly ?RuleConditions $ruleConditions,
        private readonly ?Storefront $storefront,
        private readonly ?Tax $tax,
        private readonly ?ShippingMethods $shippingMethods,
        private readonly ?Gateways $gateways,
    ) {
    }

    public static function validate(string $fileContent, string $file): void
    {
        try {
            $doc = XmlUtils::parse($fileContent, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($file, $e->getMessage());
        }

        self::create($doc, $file);
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        return self::create($doc, $xmlFile);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
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
        return $this->permissions;
    }

    public function getAllowedHosts(): ?AllowedHosts
    {
        return $this->allowedHosts;
    }

    /**
     * @param array<string, list<string>> $permission
     */
    public function addPermissions(array $permission): void
    {
        if ($this->permissions === null) {
            $this->permissions = Permissions::fromArray([
                'permissions' => [],
            ]);
        }

        $this->permissions->add($permission);
    }

    public function getCustomFields(): ?CustomFields
    {
        return $this->customFields;
    }

    public function getWebhooks(): ?Webhooks
    {
        return $this->webhooks;
    }

    public function getCookies(): ?Cookies
    {
        return $this->cookies;
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
        return $this->storefront;
    }

    public function getTax(): ?Tax
    {
        return $this->tax;
    }

    public function getGateways(): ?Gateways
    {
        return $this->gateways;
    }

    /**
     * @return array<string> all hosts referenced in the manifest file
     */
    public function getAllHosts(): array
    {
        $hosts = $this->allowedHosts ? $this->allowedHosts->getHosts() : [];

        $urls = [];
        if ($this->setup) {
            $urls[] = $this->setup->getRegistrationUrl();
        }

        if ($this->webhooks) {
            $urls = \array_merge($urls, $this->webhooks->getUrls());
        }

        if ($this->admin) {
            $urls = \array_merge($urls, $this->admin->getUrls());
        }

        if ($this->payments) {
            $urls = \array_merge($urls, $this->payments->getUrls());
        }

        if ($this->tax) {
            $urls = \array_merge($urls, $this->tax->getUrls());
        }

        $urls = \array_map(fn (string $url) => (string) \parse_url($url, \PHP_URL_HOST), $urls);

        return \array_values(\array_unique(\array_merge($hosts, $urls)));
    }

    public function getShippingMethods(): ?ShippingMethods
    {
        return $this->shippingMethods;
    }

    public function isManagedByComposer(): bool
    {
        return $this->managedByComposer;
    }

    public function setManagedByComposer(bool $managedByComposer): void
    {
        $this->managedByComposer = $managedByComposer;
    }

    private static function create(\DOMDocument $doc, string $xmlFile): self
    {
        try {
            $meta = $doc->getElementsByTagName('meta')->item(0);
            \assert($meta !== null);
            $metadata = Metadata::fromXml($meta);
            $setup = $doc->getElementsByTagName('setup')->item(0);
            $setup = $setup === null ? null : Setup::fromXml($setup);
            $admin = $doc->getElementsByTagName('admin')->item(0);
            $admin = $admin === null ? null : Admin::fromXml($admin);
            $permissions = $doc->getElementsByTagName('permissions')->item(0);
            $permissions = $permissions === null ? null : Permissions::fromXml($permissions);
            $allowedHosts = $doc->getElementsByTagName('allowed-hosts')->item(0);
            $allowedHosts = $allowedHosts === null ? null : AllowedHosts::fromXml($allowedHosts);
            $customFields = $doc->getElementsByTagName('custom-fields')->item(0);
            $customFields = $customFields === null ? null : CustomFields::fromXml($customFields);
            $webhooks = $doc->getElementsByTagName('webhooks')->item(0);
            $webhooks = $webhooks === null ? null : Webhooks::fromXml($webhooks);
            $cookies = $doc->getElementsByTagName('cookies')->item(0);
            $cookies = $cookies === null ? null : Cookies::fromXml($cookies);
            $payments = $doc->getElementsByTagName('payments')->item(0);
            $payments = $payments === null ? null : Payments::fromXml($payments);
            $ruleConditions = $doc->getElementsByTagName('rule-conditions')->item(0);
            $ruleConditions = $ruleConditions === null ? null : RuleConditions::fromXml($ruleConditions);
            $storefront = $doc->getElementsByTagName('storefront')->item(0);
            $storefront = $storefront === null ? null : Storefront::fromXml($storefront);
            $tax = $doc->getElementsByTagName('tax')->item(0);
            $tax = $tax === null ? null : Tax::fromXml($tax);
            $shippingMethods = $doc->getElementsByTagName('shipping-methods')->item(0);
            $shippingMethods = $shippingMethods === null ? null : ShippingMethods::fromXml($shippingMethods);
            $gateways = $doc->getElementsByTagName('gateways')->item(0);
            $gateways = $gateways === null ? null : Gateways::fromXml($gateways);
        } catch (\Exception $e) {
            throw AppException::xmlParsingException($xmlFile, $e->getMessage());
        }

        return new self(
            \dirname($xmlFile),
            $metadata,
            $setup,
            $admin,
            $permissions,
            $allowedHosts,
            $customFields,
            $webhooks,
            $cookies,
            $payments,
            $ruleConditions,
            $storefront,
            $tax,
            $shippingMethods,
            $gateways
        );
    }
}
