<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Update\Services\Download;
use Shopware\Core\Framework\Update\Struct\Version;

class DownloadStep
{
    /**
     * @var Version
     */
    private $version;

    /**
     * @var string
     */
    private $destination;

    public function __construct(Version $version, string $destination)
    {
        $this->version = $version;
        $this->destination = $destination;
    }

    /**
     * @return FinishResult|ValidResult
     */
    public function run(int $offset): object
    {
        if (is_file($this->destination) && filesize($this->destination) > 0) {
            return new FinishResult($offset, $this->version->size);
        }

        $download = new Download();
        $startTime = microtime(true);
        $download->setHaltCallback(function () use ($startTime) {
            return microtime(true) - $startTime > 10;
        });
        $offset = $download->downloadFile($this->version->uri, $this->destination, (int) $this->version->size, $this->version->sha1);

        return new ValidResult($offset, (int) $this->version->size);
    }
}
