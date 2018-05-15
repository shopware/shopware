<?php declare(strict_types=1);

namespace Shopware\System\User\Struct;

use Shopware\Api\Entity\Entity;

class UserBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $encoder;

    /**
     * @var string|null
     */
    protected $apiKey;

    /**
     * @var string|null
     */
    protected $sessionId;

    /**
     * @var \DateTime|null
     */
    protected $lastLogin;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $failedLogins;

    /**
     * @var \DateTime|null
     */
    protected $lockedUntil;

    /**
     * @var bool
     */
    protected $extendedEditor;

    /**
     * @var bool
     */
    protected $disabledCache;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getEncoder(): string
    {
        return $this->encoder;
    }

    public function setEncoder(string $encoder): void
    {
        $this->encoder = $encoder;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTime $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getFailedLogins(): int
    {
        return $this->failedLogins;
    }

    public function setFailedLogins(int $failedLogins): void
    {
        $this->failedLogins = $failedLogins;
    }

    public function getLockedUntil(): ?\DateTime
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTime $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function getExtendedEditor(): bool
    {
        return $this->extendedEditor;
    }

    public function setExtendedEditor(bool $extendedEditor): void
    {
        $this->extendedEditor = $extendedEditor;
    }

    public function getDisabledCache(): bool
    {
        return $this->disabledCache;
    }

    public function setDisabledCache(bool $disabledCache): void
    {
        $this->disabledCache = $disabledCache;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
