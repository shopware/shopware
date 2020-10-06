<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest;

use Shopware\Core\Framework\App\Manifest\Xml\Admin;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFields;
use Shopware\Core\Framework\App\Manifest\Xml\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\Manifest\Xml\Setup;
use Shopware\Core\Framework\App\Manifest\Xml\Webhooks;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\Config\Util\XmlUtils;

class Manifest
{
    private const XSD_FILE = __DIR__ . '/Schema/manifest-1.0.xsd';

    /**
     * @var string
     */
    private $path;

    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var Setup|null
     */
    private $setup;

    /**
     * @var Admin|null
     */
    private $admin;

    /**
     * @var Permissions|null
     */
    private $permissions;

    /**
     * @var CustomFields|null
     */
    private $customFields;

    /**
     * @var Webhooks|null
     */
    private $webhooks;

    private function __construct(
        string $path,
        Metadata $metadata,
        ?Setup $setup,
        ?Admin $admin,
        ?Permissions $permissions,
        ?CustomFields $customFields,
        ?Webhooks $webhooks
    ) {
        $this->path = $path;
        $this->metadata = $metadata;
        $this->setup = $setup;
        $this->admin = $admin;
        $this->permissions = $permissions;
        $this->customFields = $customFields;
        $this->webhooks = $webhooks;
    }

    public static function createFromXmlFile(string $xmlFile): self
    {
        try {
            $doc = XmlUtils::loadFile($xmlFile, self::XSD_FILE);
        } catch (\Exception $e) {
            throw new XmlParsingException($xmlFile, $e->getMessage());
        }

        /** @var \DOMElement $meta */
        $meta = $doc->getElementsByTagName('meta')->item(0);
        $metadata = Metadata::fromXml($meta);
        $setup = $doc->getElementsByTagName('setup')->item(0);
        $setup = $setup === null ? null : Setup::fromXml($setup);
        $admin = $doc->getElementsByTagName('admin')->item(0);
        $admin = $admin === null ? null : Admin::fromXml($admin);
        $permissions = $doc->getElementsByTagName('permissions')->item(0);
        $permissions = $permissions === null ? null : Permissions::fromXml($permissions);
        $customFields = $doc->getElementsByTagName('custom-fields')->item(0);
        $customFields = $customFields === null ? null : CustomFields::fromXml($customFields);
        $webhooks = $doc->getElementsByTagName('webhooks')->item(0);
        $webhooks = $webhooks === null ? null : Webhooks::fromXml($webhooks);

        return new self(\dirname($xmlFile), $metadata, $setup, $admin, $permissions, $customFields, $webhooks);
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

    public function getCustomFields(): ?CustomFields
    {
        return $this->customFields;
    }

    public function getWebhooks(): ?Webhooks
    {
        return $this->webhooks;
    }
}
