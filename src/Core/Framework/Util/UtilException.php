<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\UtilXmlParsingException;
use Shopware\Core\System\SystemConfig\Exception\XmlElementNotFoundException;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class UtilException extends HttpException
{
    public const INVALID_JSON = 'UTIL_INVALID_JSON';
    public const INVALID_JSON_NOT_LIST = 'UTIL_INVALID_JSON_NOT_LIST';

    public const XML_PARSE_ERROR = 'UTIL__XML_PARSE_ERROR';

    public const XML_ELEMENT_NOT_FOUND = 'UTIL__XML_ELEMENT_NOT_FOUND';
    public const FILESYSTEM_FILE_NOT_FOUND = 'UTIL__FILESYSTEM_FILE_NOT_FOUND';

    public static function invalidJson(\JsonException $e): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON,
            'JSON is invalid',
            [],
            $e
        );
    }

    public static function invalidJsonNotList(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON_NOT_LIST,
            'JSON cannot be decoded to a list'
        );
    }

    public static function xmlElementNotFound(string $element): self
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new XmlElementNotFoundException($element);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::XML_ELEMENT_NOT_FOUND,
            'Unable to locate element with the name "{{ element }}".',
            ['element' => $element]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function xmlParsingException(string $file, string $message): self|XmlParsingException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new XmlParsingException($file, $message);
        }

        return new UtilXmlParsingException($file, $message);
    }

    public static function cannotFindFileInFilesystem(string $file, string $filesystem): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FILESYSTEM_FILE_NOT_FOUND,
            'The file "{{ file }}" does not exist in the given filesystem "{{ filesystem }}"',
            ['file' => $file, 'filesystem' => $filesystem]
        );
    }
}
