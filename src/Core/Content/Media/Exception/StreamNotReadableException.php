<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class StreamNotReadableException extends ShopwareHttpException
{
    public function __construct(string $path)
    {
        parent::__construct(
            'Could not read stream at following path: "{{ path }}"',
            ['path' => $path]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_STREAM_NOT_READABLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
