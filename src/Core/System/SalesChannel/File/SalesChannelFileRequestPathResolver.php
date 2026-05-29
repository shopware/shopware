<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;

/**
 * @internal
 */
#[Package('framework')]
final class SalesChannelFileRequestPathResolver
{
    public function buildTemplatePath(string $fileFamily, string $fileName): string
    {
        $this->validateFileFamily($fileFamily);
        $this->validateFileName($fileName);

        return SalesChannelFile::TEMPLATE_ROOT . '/' . $fileFamily . '/' . $fileName . SalesChannelFile::TEMPLATE_SUFFIX;
    }

    public function validateFileFamily(string $fileFamily): void
    {
        if ($fileFamily === ''
            || $fileFamily === '.'
            || $fileFamily === '..'
            || preg_match('/^[A-Za-z0-9_-]+$/', $fileFamily) !== 1
        ) {
            throw SalesChannelFileException::invalidFileFamily($fileFamily);
        }
    }

    private function validateFileName(string $path): void
    {
        if ($path === ''
            || str_starts_with($path, '/')
            || str_ends_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z]:/', $path) === 1
        ) {
            throw SalesChannelFileException::invalidPath($path);
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9._-]+$/', $segment) !== 1) {
                throw SalesChannelFileException::invalidPath($path);
            }
        }

        $fileName = (string) end($segments);
        if (pathinfo($fileName, \PATHINFO_EXTENSION) === '' || pathinfo($fileName, \PATHINFO_FILENAME) === '') {
            throw SalesChannelFileException::invalidPath($path);
        }
    }
}
