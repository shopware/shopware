<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class DuplicateCriteriaKeyException extends ShopwareHttpException
{
    public function __construct(string $key, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The key "%s" is duplicated in the criteria collection.', $key);

        parent::__construct($message, $code, $previous);
    }
}
