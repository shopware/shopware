<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class IllegalUrlException extends ShopwareHttpException
{
    public function __construct(string $url)
    {
        parent::__construct(
            'Provided URL "{{ url }}" is not allowed.',
            ['url' => $url]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_ILLEGAL_URL';
    }
}
