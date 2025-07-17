<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Psr\Http\Message\StreamInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FilePutContentsMock
{
    /**
     * @var list<string>
     */
    public static array $fileNames = [];

    public static function reset(): void
    {
        self::$fileNames = [];
    }
}

function file_put_contents(string $filename, mixed $body): void
{
    FilePutContentsMock::$fileNames[] = $filename;
    \assert($body instanceof StreamInterface);
}
