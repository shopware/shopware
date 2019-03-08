<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StoreLicenseSubscriptionStruct extends Struct
{
    /**
     * @var \DateTimeInterface
     */
    protected $expirationDate;
}
