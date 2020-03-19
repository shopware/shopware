<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Struct\Struct;

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
     * @var string
     */
    public $uri;

    /**
     * @var int
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
