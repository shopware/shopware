<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DuplicateAppSnippetKeysException extends ShopwareHttpException
{
    /**
     * @param array<string|array> $files
     */
    public function __construct(array $files)
    {
        parent::__construct(
            'Administration snippets provided via apps contain illegal duplicates: {files}',
            ['files' => $files]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DUPLICATE_APP_SNIPPET_KEYS';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
