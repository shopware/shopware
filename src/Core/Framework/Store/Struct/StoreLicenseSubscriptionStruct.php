<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseSubscriptionStruct extends Struct
{
    /**
     * @var \DateTime
     */
    private $expirationDate;

    public function __construct(\DateTime $expirationDate)
    {
        $this->expirationDate = $expirationDate;
    }

    public function getExpirationDate(): \DateTime
    {
        return $this->expirationDate;
    }

    public function toArray(): array
    {
        return [
            'expirationDate' => $this->getExpirationDate()->format(DATE_ATOM),
        ];
    }
}
