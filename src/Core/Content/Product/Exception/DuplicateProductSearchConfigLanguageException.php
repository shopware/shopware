<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class DuplicateProductSearchConfigLanguageException extends ShopwareHttpException
{
    public function __construct(
        string $languageId,
        \Throwable $e
    ) {
        parent::__construct(
            'Product search config with language_id {{ languageId }} already exists.',
            ['languageId' => $languageId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_PRODUCT_SEARCH_CONFIG_LANGUAGE_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
