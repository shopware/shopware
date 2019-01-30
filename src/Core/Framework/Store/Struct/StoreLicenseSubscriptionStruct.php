<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseSubscriptionStruct extends Struct
{
    /**
     * @var \DateTime
     */
    private $expirationDate;

    /**
     * StoreLicenseSubscriptionStruct constructor.
     *
     * @param \DateTime $expirationDate
     */
    public function __construct(\DateTime $expirationDate)
    {
        $this->expirationDate = $expirationDate;
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate(): \DateTime
    {
        return $this->expirationDate;
    }
}
