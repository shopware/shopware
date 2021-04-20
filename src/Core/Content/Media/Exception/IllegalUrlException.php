<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class IllegalUrlException extends ShopwareHttpException
{
    public function __construct(string $url, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Provided URL "{{ url }}" is not allowed.',
            ['url' => $url],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_ILLEGAL_URL';
    }
}
