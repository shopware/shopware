<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Shopware\Core\System\Touchpoint\TouchpointCollection;
use Shopware\Core\System\User\UserCollection;

class LocaleStruct extends Entity
{
    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $territory;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LocaleTranslationCollection|null
     */
    protected $translations;

    /**
     * @var UserCollection|null
     */
    protected $users;

    /**
     * @var TouchpointCollection|null
     */
    protected $touchpoints;

    /**
     * @var TouchpointCollection|null
     */
    protected $fallbackTouchpoints;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTerritory(): string
    {
        return $this->territory;
    }

    public function setTerritory(string $territory): void
    {
        $this->territory = $territory;
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

    public function getTranslations(): ?LocaleTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(LocaleTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function getTouchpoints(): ?TouchpointCollection
    {
        return $this->touchpoints;
    }

    public function setTouchpoints(TouchpointCollection $touchpoints): void
    {
        $this->touchpoints = $touchpoints;
    }

    public function getFallbackTouchpoints(): ?TouchpointCollection
    {
        return $this->fallbackTouchpoints;
    }

    public function setFallbackTouchpoints(TouchpointCollection $fallbackTouchpoints): void
    {
        $this->fallbackTouchpoints = $fallbackTouchpoints;
    }
}
