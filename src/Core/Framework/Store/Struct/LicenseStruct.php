<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
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
     * @var string
     */
    protected $paymentText;

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

    protected ?\DateTimeInterface $expirationDate = null;

    protected ?StoreLicenseSubscriptionStruct $subscription = null;

    protected bool $trialPhaseIncluded = false;

    /**
     * @var ?array{discountedPrice: float, firstDateOfFullCharging: string}
     */
    protected ?array $discountInformation = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): LicenseStruct
    {
        $license = (new self())->assign($data);

        if (isset($data['creationDate']) && \is_string($data['creationDate'])) {
            $license->setCreationDate(new \DateTimeImmutable($data['creationDate']));
        }

        if (isset($data['nextBookingDate']) && \is_string($data['nextBookingDate'])) {
            $license->setNextBookingDate(new \DateTimeImmutable($data['nextBookingDate']));
        }

        return $license;
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

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function getSubscription(): ?StoreLicenseSubscriptionStruct
    {
        return $this->subscription;
    }

    public function setSubscription(?StoreLicenseSubscriptionStruct $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function isTrialPhaseIncluded(): bool
    {
        return $this->trialPhaseIncluded;
    }

    public function setTrialPhaseIncluded(bool $trialPhaseIncluded): void
    {
        $this->trialPhaseIncluded = $trialPhaseIncluded;
    }

    /**
     * @return ?array{discountedPrice: float, firstDateOfFullCharging: string}
     */
    public function getDiscountInformation(): ?array
    {
        return $this->discountInformation;
    }

    /**
     * @param ?array{discountedPrice: float, firstDateOfFullCharging: string} $discountInformation
     */
    public function setDiscountInformation(?array $discountInformation): void
    {
        $this->discountInformation = $discountInformation;
    }
}
