<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ThemeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var array|null
     */
    protected $values;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var object
     */
    protected $salesChannelTheme;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getValues(): ?array
    {
        return $this->values;
    }

    public function setValues(?array $values): void
    {
        $this->values = $values;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getSalesChannelTheme(): object
    {
        return $this->salesChannelTheme;
    }

    public function setSalesChannelTheme(object $salesChannelTheme): void
    {
        $this->salesChannelTheme = $salesChannelTheme;
    }
}
