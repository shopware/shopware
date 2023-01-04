<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('system-settings')]
class Version extends Struct
{
    /**
     * @var string
     */
    public $version;

    /**
     * @var bool
     */
    public $isNewer;

    /**
     * @var array
     */
    public $changelog;

    /**
     * @var non-empty-string
     */
    public $uri;

    /**
     * @var string
     */
    public $size;

    /**
     * @var string
     */
    public $sha1;

    /**
     * @var string
     */
    public $sha256;

    /**
     * @var array
     */
    public $checks;

    public function getApiAlias(): string
    {
        return 'update_api_version';
    }
}
