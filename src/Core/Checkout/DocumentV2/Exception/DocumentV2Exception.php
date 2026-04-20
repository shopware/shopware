<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Exception;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentV2Exception extends HttpException
{
    public const UNKNOWN_RENDER_DATA = 'DOCUMENT_V2__UNKNOWN_RENDER_DATA';

    public const UNKNOWN_RENDER_RESULT = 'DOCUMENT_V2__UNKNOWN_RENDER_RESULT';

    public const DUPLICATE_RENDER_RESULT = 'DOCUMENT_V2__DUPLICATE_RENDER_RESULT';

    public static function unknownRenderData(string $key, string $expectedClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_RENDER_DATA,
            'Unknown render data for key "{{ key }}", expected instance of "{{ expectedClass }}".',
            ['key' => $key, 'expectedClass' => $expectedClass],
        );
    }

    public static function unknownRenderResult(DocumentFormat $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_RENDER_RESULT,
            'Unknown render result for format "{{ format }}".',
            ['format' => $format],
        );
    }

    public static function duplicateRenderResult(DocumentFormat $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_RENDER_RESULT,
            'Duplicate render result for format "{{ format }}".',
            ['format' => $format],
        );
    }
}
