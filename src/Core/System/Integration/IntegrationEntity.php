<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class IntegrationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $secretAccessKey;

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     *
     * @var bool
     */
    protected $admin;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_3722) tag:v6.4.0
     *
     * @var bool
     */
    protected $writeAccess;

    /**
     * @var \DateTimeInterface|null
     */
    protected $lastUsageAt;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var AppEntity|null
     */
    protected $app;

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     *
     * @var AclRoleCollection|null
     */
    protected $aclRoles;

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function setSecretAccessKey(string $secretAccessKey): void
    {
        $this->secretAccessKey = $secretAccessKey;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_3722) tag:v6.4.0
     */
    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_3722) tag:v6.4.0
     */
    public function setWriteAccess(bool $writeAccess): void
    {
        $this->writeAccess = $writeAccess;
    }

    public function getLastUsageAt(): ?\DateTimeInterface
    {
        return $this->lastUsageAt;
    }

    public function setLastUsageAt(\DateTimeInterface $lastUsageAt): void
    {
        $this->lastUsageAt = $lastUsageAt;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     */
    public function getAclRoles(): ?AclRoleCollection
    {
        return $this->aclRoles;
    }

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     */
    public function setAclRoles(AclRoleCollection $aclRoles): void
    {
        $this->aclRoles = $aclRoles;
    }

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     */
    public function getAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * @internal (flag:FEATURE_NEXT_3722)
     */
    public function setAdmin(bool $admin): void
    {
        $this->admin = $admin;
    }
}
