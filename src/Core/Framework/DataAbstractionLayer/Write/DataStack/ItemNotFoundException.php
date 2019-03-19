<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack;

use Shopware\Core\Framework\ShopwareHttpException;

class ItemNotFoundException extends ShopwareHttpException
{
    public function __construct(string $key)
    {
        parent::__construct('Item {{ key }} not found in data stack.', ['key' => $key]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DATASTACK_ITEM_NOT_FOUND';
    }
}
