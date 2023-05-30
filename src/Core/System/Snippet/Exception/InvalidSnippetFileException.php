<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class InvalidSnippetFileException extends ShopwareHttpException
{
    public function __construct(string $locale)
    {
        parent::__construct(
            'The base snippet file for locale {{ locale }} is not registered.',
            ['locale' => $locale]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_SNIPPET_FILE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
