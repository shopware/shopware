<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\ComparatorException;
use Shopware\Core\Framework\Util\Exception\UtilXmlParsingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class UtilException extends HttpException
{
    public const INVALID_JSON = 'UTIL_INVALID_JSON';
    public const INVALID_JSON_NOT_LIST = 'UTIL_INVALID_JSON_NOT_LIST';
    public const XML_PARSE_ERROR = 'UTIL__XML_PARSE_ERROR';
    public const XML_ELEMENT_NOT_FOUND = 'UTIL__XML_ELEMENT_NOT_FOUND';
    public const FILESYSTEM_FILE_NOT_FOUND = 'UTIL__FILESYSTEM_FILE_NOT_FOUND';
    public const COULD_NOT_HASH_FILE = 'UTIL__COULD_NOT_HASH_FILE';
    public const OPERATOR_NOT_SUPPORTED = 'UTIL__OPERATOR_NOT_SUPPORTED';

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
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::XML_ELEMENT_NOT_FOUND,
            'Unable to locate element with the name "{{ element }}".',
            ['element' => $element]
        );
    }

    public static function xmlParsingException(string $file, string $message): self
    {
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

    public static function couldNotHashFile(string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COULD_NOT_HASH_FILE,
            'Could not generate hash for  "{{ file }}"',
            ['file' => $file]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function operatorNotSupported(string $operator): self|ComparatorException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return ComparatorException::operatorNotSupported($operator);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OPERATOR_NOT_SUPPORTED,
            'Operator "{{ operator }}" is not supported.',
            ['operator' => $operator]
        );
    }
}
