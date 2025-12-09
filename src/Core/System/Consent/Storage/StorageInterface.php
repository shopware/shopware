<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Storage;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;

/**
 * @internal
 */
#[Package('data-services')]
interface StorageInterface
{
    public static function code(): string;

    public function status(string $name, string $identifier): ConsentStateDTO;

    public function accept(string $name, string $identifier): void;

    public function revoke(string $name, string $identifier): void;
}
