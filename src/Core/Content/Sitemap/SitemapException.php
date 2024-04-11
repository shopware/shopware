<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class SitemapException extends HttpException
{
    public const FILE_NOT_READABLE = 'CONTENT__FILE_IS_NOT_READABLE';

    public static function fileNotReadable(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FILE_NOT_READABLE,
            'File is not readable at {{ path }}.',
            ['path' => $path]
        );
    }
}
