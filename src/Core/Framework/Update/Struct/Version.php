<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('system-settings')]
class Version extends Struct
{
    public string $version = '';

    public bool $isNewer = false;

    public string $changelog = '';

    public string $url = '';

    public \DateTimeImmutable $createdAt;

    public string $name = '';

    public function getApiAlias(): string
    {
        return 'update_api_version';
    }
}
