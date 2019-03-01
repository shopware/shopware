<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class ThumbnailCouldNotBeSavedException extends ShopwareHttpException
{
    protected $code = 'THUMBNAIL_NOT_SAVED_EXCEPTION';

    public function __construct(string $url, int $code = 0, ?\Throwable $previous = null)
    {
        $message = 'Thumbnail could not be saved to location: ' . $url;
        parent::__construct($message, $code, $previous);
    }
}
