<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class ElasticsearchAdminException extends HttpException
{
    public const ADMIN_ELASTIC_SEARCH_NOT_ENABLED = 'ELASTICSEARCH__ADMIN_ES_NOT_ENABLED';
    public const TERM_PARAMETER_IS_MISSING = 'ELASTICSEARCH__TERM_PARAMETER_IS_MISSING';

    public static function esNotEnabled(): self
    {
        return new self(
            Response::HTTP_SERVICE_UNAVAILABLE,
            self::ADMIN_ELASTIC_SEARCH_NOT_ENABLED,
            'Admin elasticsearch is not enabled',
        );
    }

    public static function missingTermParameter(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TERM_PARAMETER_IS_MISSING,
            'Parameter "term" is missing.',
        );
    }
}
