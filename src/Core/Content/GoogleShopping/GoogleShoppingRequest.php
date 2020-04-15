<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class GoogleShoppingRequest
{
    /**
     * @var GoogleShoppingAccountEntity
     */
    private $googleShoppingAccount;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SalesChannelEntity
     */
    private $salesChannel;

    public function __construct(
        Context $context,
        SalesChannelEntity $salesChannel
    ) {
        $this->context = $context;
        $this->salesChannel = $salesChannel;
        $this->googleShoppingAccount = $salesChannel->getGoogleShoppingAccount();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @return GoogleShoppingAccountEntity
     */
    public function getGoogleShoppingAccount(): ?GoogleShoppingAccountEntity
    {
        return $this->googleShoppingAccount;
    }
}
