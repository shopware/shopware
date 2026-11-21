<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @codeCoverageIgnore
 */
final class WireNamesRequest extends AbstractRequest
{
    public const DEFAULT_BODY_NAME = 'body';

    public const DEFAULT_CAMEL_CASE = '';

    public const DEFAULT_HTTP_STATUS = 200;

    public const DEFAULT_QUERY_NAME = 'it\'s\\query';

    #[SerializedName('body-name')]
    public string $bodyName;

    public string $camelCase;

    #[SerializedName('HTTPStatus')]
    public int $hTTPStatus;

    #[SerializedName('query-name')]
    public string $queryName;

    /**
     * @internal
     */
    public function __construct(
    ) {
    }
}
