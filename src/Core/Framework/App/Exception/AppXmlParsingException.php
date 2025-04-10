<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class AppXmlParsingException extends AppException
{
    public static function cannotParseFile(string $xmlFile, string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message],
        );
    }

    public static function cannotParseContent(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse XML content. Message: {{ message }}',
            ['message' => $message],
        );
    }
}
