<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class DependencyInjectionException extends HttpException
{
    public const PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';
    public const BUNDLES_METADATA_IS_NOT_AN_ARRAY = 'FRAMEWORK__BUNDLES_METADATA_IS_NOT_AN_ARRAY';
    public const TAGGED_SERVICE_HAS_WRONG_TYPE = 'FRAMEWORK__TAGGED_SERVICE_HAS_WRONG_TYPE';
    public const PARAMETER_HAS_WRONG_TYPE = 'FRAMEWORK__PARAMETER_HAS_WRONG_TYPE';
    public const MISSING_ENTITY_TAG_ATTRIBUTE = 'FRAMEWORK__MISSING_ENTITY_TAG_ATTRIBUTE';
    public const ENTITY_TAG_MISMATCH = 'FRAMEWORK__ENTITY_TAG_MISMATCH';
    public const ENTITY_TAG_UNRESOLVABLE = 'FRAMEWORK__ENTITY_TAG_UNRESOLVABLE';

    public static function projectDirNotInContainer(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROJECT_DIR_IS_NOT_A_STRING,
            'Container parameter "kernel.project_dir" needs to be a string'
        );
    }

    public static function bundlesMetadataIsNotAnArray(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::BUNDLES_METADATA_IS_NOT_AN_ARRAY,
            'Container parameter "kernel.bundles_metadata" needs to be an array'
        );
    }

    public static function taggedServiceHasWrongType(string $service, string $tag, string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TAGGED_SERVICE_HAS_WRONG_TYPE,
            \sprintf('Service "%s" is tagged as "%s" and must therefore be of type "%s".', $service, $tag, $type)
        );
    }

    public static function parameterHasWrongType(string $parameter, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PARAMETER_HAS_WRONG_TYPE,
            \sprintf('Parameter "%s" should be: "%s". Got: "%s"', $parameter, $expectedType, $actualType)
        );
    }

    public static function missingEntityTagAttribute(string $serviceId, string $tagName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_ENTITY_TAG_ATTRIBUTE,
            \sprintf('Service "%s" is tagged as "%s" but is missing the required "entity" attribute.', $serviceId, $tagName)
        );
    }

    public static function entityTagUnresolvable(string $serviceId, string $tagName, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENTITY_TAG_UNRESOLVABLE,
            \sprintf(
                'Service "%s" is tagged as "%s" but has no "entity" attribute and the entity name could not be resolved from class "%s".',
                $serviceId,
                $tagName,
                $class
            )
        );
    }

    public static function entityTagMismatch(string $serviceId, string $tagName, string $tagEntity, string $actualEntity): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENTITY_TAG_MISMATCH,
            \sprintf(
                'Service "%s" has tag "%s" with entity="%s", but getEntityName() returns "%s". They must match.',
                $serviceId,
                $tagName,
                $tagEntity,
                $actualEntity
            )
        );
    }
}
