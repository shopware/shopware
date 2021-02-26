<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageOfNewsletterDeleteException extends ShopwareHttpException
{
    public function __construct(string $language, ?\Throwable $e = null)
    {
        parent::__construct(
            'The language "{{ language }}" cannot be deleted because newsletter recipients with this language exist.',
            ['language' => $language],
            $e
        );
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
