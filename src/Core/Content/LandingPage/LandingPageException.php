<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class LandingPageException extends HttpException
{
    public const EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND = 'CONTENT__LANDING_PAGE_NOT_FOUND';

    public static function notFound(string $id): self
    {
        return new self(
            404,
            self::EXCEPTION_CODE_LANDING_PAGE_NOT_FOUND,
            \sprintf('Landing page with id "%s" not found.', $id)
        );
    }
}
