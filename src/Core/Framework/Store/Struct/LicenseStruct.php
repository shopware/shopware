<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class LicenseStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var \DateTimeImmutable
     */
    protected $creationDate;

    /**
     * @var string
     */
    protected $variant;

    /**
     * @var float
     */
    protected $netPrice;

    /**
     * @var \DateTimeImmutable|null null for free extensions
     */
    protected $nextBookingDate;

    /**
     * @var ExtensionStruct
     */
    protected $licensedExtension;

    public static function fromArray(array $data): LicenseStruct
    {
        return (new self())->assign($data);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCreationDate(): \DateTimeImmutable
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeImmutable $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function setVariant(string $variant): void
    {
        $this->variant = $variant;
    }

    public function getNetPrice(): float
    {
        return $this->netPrice;
    }

    public function setNetPrice(float $netPrice): void
    {
        $this->netPrice = $netPrice;
    }

    public function getNextBookingDate(): ?\DateTimeImmutable
    {
        return $this->nextBookingDate;
    }

    public function setNextBookingDate(\DateTimeImmutable $nextBookingDate): void
    {
        $this->nextBookingDate = $nextBookingDate;
    }

    public function getLicensedExtension(): ExtensionStruct
    {
        return $this->licensedExtension;
    }

    public function setLicensedExtension(ExtensionStruct $licensedExtension): void
    {
        $this->licensedExtension = $licensedExtension;
    }
}
