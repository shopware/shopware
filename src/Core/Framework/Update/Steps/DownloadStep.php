<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\Download;
use Shopware\Core\Framework\Update\Struct\Version;

#[Package('system-settings')]
class DownloadStep
{
    public function __construct(
        private readonly Version $version,
        private readonly string $destination,
        private readonly bool $testMode = false
    ) {
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
        $download->setHaltCallback(static fn () => microtime(true) - $startTime > 10);

        $offset = $download->downloadFile($this->version->uri, $this->destination, (int) $this->version->size, $this->version->sha1);

        return new ValidResult($offset, (int) $this->version->size);
    }
}
