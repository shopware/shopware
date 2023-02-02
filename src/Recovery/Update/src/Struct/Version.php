<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Struct;

class Version extends Struct
{
    /**
     * @var string Semver compatible version e.G 6.0.0-rc
     */
    public $version;

    /**
     * @var string Type of package eg zip
     */
    public $type;

    /**
     * @var string uri to update package
     */
    public $uri;

    /**
     * @var string sha1 sum of update
     */
    public $sha1;

    /**
     * @var int size in bytes
     */
    public $size;
}
