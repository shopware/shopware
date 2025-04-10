<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class SitemapException extends HttpException
{
    public const FILE_NOT_READABLE = 'CONTENT__FILE_IS_NOT_READABLE';

    public const SITEMAP_ALREADY_LOCKED = 'CONTENT__SITEMAP_ALREADY_LOCKED';

    public const INVALID_DOMAIN = 'CONTENT__INVALID_DOMAIN';

    public static function fileNotReadable(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FILE_NOT_READABLE,
            'File is not readable at {{ path }}.',
            ['path' => $path]
        );
    }

    public static function sitemapAlreadyLocked(SalesChannelContext $context): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SITEMAP_ALREADY_LOCKED,
            'Cannot acquire lock for sales channel {{salesChannelId}} and language {{languageId}}',
            [
                'salesChannelId' => $context->getSalesChannelId(),
                'languageId' => $context->getLanguageId(),
            ],
        );
    }

    public static function invalidDomain(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOMAIN,
            'Invalid domain',
        );
    }
}
