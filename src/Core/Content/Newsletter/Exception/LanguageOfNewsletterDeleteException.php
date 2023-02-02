<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class LanguageOfNewsletterDeleteException extends ShopwareHttpException
{
    public function __construct(?\Throwable $e = null)
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
