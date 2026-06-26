<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class StoreLicenseSubscriptionStruct extends Struct
{
    protected \DateTimeInterface $expirationDate;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $subscription = new self();

        if (isset($data['expirationDate']) && \is_string($data['expirationDate'])) {
            $subscription->setExpirationDate(new \DateTimeImmutable($data['expirationDate']));
            unset($data['expirationDate']);
        }

        $subscription->assign($data);

        return $subscription;
    }

    public function getExpirationDate(): \DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(\DateTimeInterface $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function getApiAlias(): string
    {
        return 'store_license_subscription';
    }
}
