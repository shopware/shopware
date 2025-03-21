<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Exception\CustomEntityXmlParsingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class CustomEntityException extends HttpException
{
    public const CUSTOM_ENTITY_ON_DELETE_PROPERTY_NOT_SUPPORTED = 'FRAMEWORK__CUSTOM_ENTITY_ON_DELETE_PROPERTY_NOT_SUPPORTED';
    public const CUSTOM_FIELDS_AWARE_NO_LABEL_PROPERTY = 'NO_LABEL_PROPERTY';
    public const CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_NOT_DEFINED = 'LABEL_PROPERTY_NOT_DEFINED';
    public const CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_WRONG_TYPE = 'LABEL_PROPERTY_WRONG_TYPE';

    public const XML_PARSE_ERROR = 'SYSTEM_CUSTOM_ENTITY__XML_PARSE_ERROR';

    public const NOT_FOUND = 'FRAMEWORK__CUSTOM_ENTITY_NOT_FOUND';

    public static function noLabelProperty(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::CUSTOM_FIELDS_AWARE_NO_LABEL_PROPERTY, 'Entity must have a label property when it is custom field aware');
    }

    public static function labelPropertyNotDefined(string $labelProperty): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_NOT_DEFINED, 'Entity label_property "{{ labelProperty }}" is not defined in fields', ['labelProperty' => $labelProperty]);
    }

    public static function labelPropertyWrongType(string $labelProperty): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::CUSTOM_FIELDS_AWARE_LABEL_PROPERTY_WRONG_TYPE, 'Entity label_property "{{ labelProperty }}" must be a string field', ['labelProperty' => $labelProperty]);
    }

    public static function notFound(string $entityName): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::NOT_FOUND, 'Custom entity "{{ entityName }}" not found', ['entityName' => $entityName]);
    }

    public static function xmlParsingException(string $file, string $message): self
    {
        return new CustomEntityXmlParsingException($file, $message);
    }

    public static function unsupportedOnDeletePropertyOnField(string $onDelete, string $name): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CUSTOM_ENTITY_ON_DELETE_PROPERTY_NOT_SUPPORTED,
            'onDelete property {{ onDelete }} are not supported on field {{ name }}',
            ['onDelete' => $onDelete, 'name' => $name]
        );
    }
}
