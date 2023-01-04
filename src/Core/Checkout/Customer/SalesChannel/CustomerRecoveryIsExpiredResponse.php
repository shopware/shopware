<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('customer-order')]
class CustomerRecoveryIsExpiredResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<string, bool>
     */
    protected $object;

    public function __construct(bool $expired)
    {
        parent::__construct(new ArrayStruct(['isExpired' => $expired]));
    }

    public function isExpired(): bool
    {
        return $this->object->get('isExpired');
    }
}
