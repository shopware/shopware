<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class FrameworkException extends HttpException
{
    private const PROJECT_DIR_NOT_EXISTS = 'FRAMEWORK__PROJECT_DIR_NOT_EXISTS';

    private const INVALID_COLLECTION_ELEMENT_TYPE = 'FRAMEWORK__INVALID_COLLECTION_ELEMENT_TYPE';

    public static function projectDirNotExists(string $dir, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_NOT_EXISTS,
            'Project directory "{{ dir }}" does not exist.',
            ['dir' => $dir],
            $e
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return 'self' in the future
     */
    public static function collectionElementInvalidType(string $expectedClass, string $elementClass): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \InvalidArgumentException(
                \sprintf('Expected collection element of type %s got %s', $expectedClass, $elementClass)
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COLLECTION_ELEMENT_TYPE,
            'Expected collection element of type {{ expected }} got {{ element }}',
            ['expected' => $expectedClass, 'element' => $elementClass]
        );
    }
}
