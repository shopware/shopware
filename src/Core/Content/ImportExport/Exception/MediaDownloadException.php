<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class MediaDownloadException extends ShopwareHttpException
{
    public function __construct(?string $url)
    {
        parent::__construct('Cannot download media from url: {{ url }}', ['url' => PathHelper::stripCredentials($url ?? 'null')]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_MEDIA_DOWNLOAD_FAILED';
    }
}
