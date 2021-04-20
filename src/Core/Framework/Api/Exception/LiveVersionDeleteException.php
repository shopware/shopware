<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class LiveVersionDeleteException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Live version can not be deleted. Delete entity instead.', [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LIVE_VERSION_DELETE';
    }
}
