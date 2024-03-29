<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class FrameworkException extends HttpException
{
    private const PROJECT_DIR_NOT_EXISTS = 'FRAMEWORK__PROJECT_DIR_NOT_EXISTS';

    private const INVALID_KERNEL_CACHE_DIR = 'FRAMEWORK__INVALID_KERNEL_CACHE_DIR';

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
}
