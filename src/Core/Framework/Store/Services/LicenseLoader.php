<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\LicenseStruct;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal
 */
class LicenseLoader
{
    public function loadFromArray(array $data): LicenseStruct
    {
        $data['extension']['permissions'] = new PermissionCollection($data['extension']['permissions'] ?? []);
        $data['licensedExtension'] = ExtensionStruct::fromArray($data['extension']);
        $data['creationDate'] = new \DateTimeImmutable($data['creationDate']);

        if (isset($data['nextBookingDate'])) {
            $data['nextBookingDate'] = $data['nextBookingDate'] ? new \DateTimeImmutable($data['nextBookingDate']) : null;
        }

        unset($data['extension']);

        return LicenseStruct::fromArray($data);
    }
}
