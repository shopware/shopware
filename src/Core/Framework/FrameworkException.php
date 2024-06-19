<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class FrameworkException extends HttpException
{
    private const PROJECT_DIR_NOT_EXISTS = 'FRAMEWORK__PROJECT_DIR_NOT_EXISTS';

    private const INVALID_KERNEL_CACHE_DIR = 'FRAMEWORK__INVALID_KERNEL_CACHE_DIR';

    private const INVALID_EVENT_DATA = 'FRAMEWORK__INVALID_EVENT_DATA';

    private const INVALID_COMPRESSION_METHOD = 'FRAMEWORK__INVALID_COMPRESSION_METHOD';
    private const EXTENSION_RESULT_NOT_SET = 'FRAMEWORK__EXTENSION_RESULT_NOT_SET';

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

    public static function invalidKernelCacheDir(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_KERNEL_CACHE_DIR,
            'Container parameter "kernel.cache_dir" needs to be a string.'
        );
    }

    public static function invalidEventData(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_EVENT_DATA,
            $message
        );
    }

    public static function invalidCompressionMethod(string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_COMPRESSION_METHOD,
            \sprintf('Invalid cache compression method: %s', $method),
        );
    }

    public static function extensionResultNotSet(string $extension): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EXTENSION_RESULT_NOT_SET,
            'Extension result not set for extension "{{ extension }}".',
            ['extension' => $extension]
        );
    }
}
