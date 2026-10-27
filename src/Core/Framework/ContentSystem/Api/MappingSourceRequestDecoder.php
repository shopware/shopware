<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\Log\Package;

/**
 * Converts a client-supplied mapping source without exposing stored-layout codec errors as HTTP 500.
 *
 * @internal
 */
#[Package('framework')]
final class MappingSourceRequestDecoder
{
    /**
     * @param array<string, mixed> $source
     */
    public static function decode(array $source): MappingSourceReference
    {
        try {
            return MappingSourceReference::fromArray($source, 'source');
        } catch (ContentSystemException $exception) {
            throw ContentSystemException::invalidMappingSource($exception);
        }
    }
}
