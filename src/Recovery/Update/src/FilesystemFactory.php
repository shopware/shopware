<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

use Gaufrette\Adapter\Ftp as FtpAdapter;
use Gaufrette\Adapter\Local as LocalAdapter;
use Gaufrette\Filesystem;

class FilesystemFactory
{
    /**
     * @var string
     */
    private $baseDir;

    /**
     * @var array
     */
    private $remoteConfig;

    /**
     * @param string $baseDir
     * @param array  $remoteConfig
     */
    public function __construct($baseDir, $remoteConfig)
    {
        $this->baseDir = $baseDir;
        $this->remoteConfig = $remoteConfig;
    }

    /**
     * @return Filesystem
     */
    public function createLocalFilesystem()
    {
        return $this->getLocalFilesystem();
    }

    /**
     * @return Filesystem
     */
    public function createRemoteFilesystem()
    {
        if (!empty($this->remoteConfig)) {
            return $this->getRemoteFilesystem();
        }

        return $this->getLocalFilesystem();
    }

    /**
     * @return Filesystem
     */
    private function getLocalFilesystem()
    {
        $adapter = new LocalAdapter($this->baseDir);

        return new Filesystem($adapter);
    }

    /**
     * @return Filesystem
     */
    private function getRemoteFilesystem()
    {
        $adapter = new FtpAdapter(
            $this->remoteConfig['path'],
            $this->remoteConfig['server'],
            [
                'username' => $this->remoteConfig['user'],
                'password' => $this->remoteConfig['password'],
            ]
        );

        return new Filesystem($adapter);
    }
}
