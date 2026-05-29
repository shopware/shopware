<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
final class SalesChannelFileException extends HttpException
{
    public const SALES_CHANNEL_FILE_INVALID_PATH = 'FRAMEWORK__SALES_CHANNEL_FILE_INVALID_PATH';
    public const SALES_CHANNEL_FILE_INVALID_FILE_FAMILY = 'FRAMEWORK__SALES_CHANNEL_FILE_INVALID_FILE_FAMILY';
    public const SALES_CHANNEL_FILE_MISSING_FILE_NAME = 'FRAMEWORK__SALES_CHANNEL_FILE_MISSING_FILE_NAME';
    public const SALES_CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES = 'FRAMEWORK__SALES_CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES';
    public const SALES_CHANNEL_FILE_NOT_FOUND = 'FRAMEWORK__SALES_CHANNEL_FILE_NOT_FOUND';

    public static function invalidPath(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_FILE_INVALID_PATH,
            'The sales channel file path "{{ path }}" is invalid.',
            ['path' => $path]
        );
    }

    public static function invalidFileFamily(string $fileFamily): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_FILE_INVALID_FILE_FAMILY,
            'The sales channel file family "{{ fileFamily }}" is invalid.',
            ['fileFamily' => $fileFamily]
        );
    }

    public static function missingFileName(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_FILE_MISSING_FILE_NAME,
            'Parameter "fileName" must be a string.'
        );
    }

    public static function invalidTemplateOverrides(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_FILE_INVALID_TEMPLATE_OVERRIDES,
            'Parameter "templateOverrides" must be an object.'
        );
    }

    public static function fileNotFound(string $fileFamily, string $fileName): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SALES_CHANNEL_FILE_NOT_FOUND,
            'Could not find sales channel file "{{ fileFamily }}/{{ fileName }}".',
            ['fileFamily' => $fileFamily, 'fileName' => $fileName]
        );
    }
}
