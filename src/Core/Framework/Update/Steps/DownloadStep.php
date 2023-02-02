<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Update\Services\Download;
use Shopware\Core\Framework\Update\Struct\Version;

class DownloadStep
{
    private Version $version;

    private string $destination;

    private bool $testMode;

    public function __construct(Version $version, string $destination, bool $testMode = false)
    {
        $this->version = $version;
        $this->destination = $destination;
        $this->testMode = $testMode;
    }

    /**
     * @return FinishResult|ValidResult
     */
    public function run(int $offset): object
    {
        if ($this->testMode === true && $offset >= 90) {
            return new FinishResult(100, 100);
        }

        if ($this->testMode === true) {
            return new ValidResult($offset + 10, 100);
        }

        if (is_file($this->destination) && filesize($this->destination) > 0) {
            return new FinishResult($offset, (int) $this->version->size);
        }

        $download = new Download();
        $startTime = microtime(true);
        $download->setHaltCallback(static function () use ($startTime) {
            return microtime(true) - $startTime > 10;
        });

        $offset = $download->downloadFile($this->version->uri, $this->destination, (int) $this->version->size, $this->version->sha1);

        return new ValidResult($offset, (int) $this->version->size);
    }
}
