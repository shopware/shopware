<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageOfNewsletterDeleteException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.5.0 - $language parameter will be removed
     */
    public function __construct(string $language = '', ?\Throwable $e = null)
    {
        parent::__construct('Language is still linked in newsletter recipients', [], $e);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__LANGUAGE_OF_NEWSLETTER_RECIPIENT_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
