<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageForeignKeyDeleteException extends ShopwareHttpException
{
    public function __construct(string $language, $e)
    {
        parent::__construct(
            'The language "{{ language }}" cannot be deleted because foreign key constraints exist.',
            ['language' => $language],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LANGUAGE_FOREIGN_KEY_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
