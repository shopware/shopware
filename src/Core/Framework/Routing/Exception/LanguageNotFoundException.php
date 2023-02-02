<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageNotFoundException extends ShopwareHttpException
{
    public const LANGUAGE_NOT_FOUND_ERROR = 'FRAMEWORK__LANGUAGE_NOT_FOUND';

    public function __construct($languageId)
    {
        parent::__construct(
            'The language "{{ languageId }}" was not found.',
            ['languageId' => $languageId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }

    public function getErrorCode(): string
    {
        return self::LANGUAGE_NOT_FOUND_ERROR;
    }
}
