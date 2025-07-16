<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FilePutContentsMock
{
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}

function file_put_contents(string $filename, mixed $data): int|false
{
    FilePutContentsMock::$calls[] = ['filename' => $filename, 'data' => $data];

    return 1;
}
