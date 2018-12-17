<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Snippet\Aggregate\SnippetSet;

use DateTime;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Snippet\SnippetCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class SnippetSetEntity extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $baseFile;

    /**
     * @var string
     */
    protected $iso;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var SnippetCollection|null
     */
    protected $snippets;

    /**
     * @var SalesChannelCollection
     */
    protected $salesChannels;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getBaseFile(): string
    {
        return $this->baseFile;
    }

    /**
     * @param string $baseFile
     */
    public function setBaseFile(string $baseFile): void
    {
        $this->baseFile = $baseFile;
    }

    /**
     * @return string
     */
    public function getIso(): string
    {
        return $this->iso;
    }

    /**
     * @param string $iso
     */
    public function setIso(string $iso): void
    {
        $this->iso = $iso;
    }

    /**
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     */
    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return null|SnippetCollection
     */
    public function getSnippets(): ?SnippetCollection
    {
        return $this->snippets;
    }

    /**
     * @param SnippetCollection $snippets
     */
    public function setSnippets(SnippetCollection $snippets): void
    {
        $this->snippets = $snippets;
    }

    /**
     * @return SalesChannelCollection
     */
    public function getSalesChannels(): SalesChannelCollection
    {
        return $this->salesChannels;
    }

    /**
     * @param SalesChannelCollection $salesChannels
     */
    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
