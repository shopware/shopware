<?php declare(strict_types=1);

namespace Shopware\ProductVote\Struct;

use Shopware\Framework\Struct\Struct;

class ProductVoteBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var string
     */
    protected $headline;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var float
     */
    protected $points;

    /**
     * @var int
     */
    protected $active;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string|null
     */
    protected $answer;

    /**
     * @var \DateTime|null
     */
    protected $answeredAt;

    /**
     * @var string|null
     */
    protected $shopUuid;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getHeadline(): string
    {
        return $this->headline;
    }

    public function setHeadline(string $headline): void
    {
        $this->headline = $headline;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getPoints(): float
    {
        return $this->points;
    }

    public function setPoints(float $points): void
    {
        $this->points = $points;
    }

    public function getActive(): int
    {
        return $this->active;
    }

    public function setActive(int $active): void
    {
        $this->active = $active;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $answer): void
    {
        $this->answer = $answer;
    }

    public function getAnsweredAt(): ?\DateTime
    {
        return $this->answeredAt;
    }

    public function setAnsweredAt(?\DateTime $answeredAt): void
    {
        $this->answeredAt = $answeredAt;
    }

    public function getShopUuid(): ?string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(?string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
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
