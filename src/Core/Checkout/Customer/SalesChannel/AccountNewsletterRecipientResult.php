<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('customer-order')]
class AccountNewsletterRecipientResult extends Struct
{
    final public const UNDEFINED = 'undefined';

    protected string $status;

    public function __construct(?string $status = null)
    {
        if ($status === null) {
            $this->status = self::UNDEFINED;

            return;
        }
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getApiAlias(): string
    {
        return 'account_newsletter_recipient';
    }
}
