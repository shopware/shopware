<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Steps;

use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\Archive\Entry\Zip as ZipEntry;
use Shopware\Core\Framework\Update\Services\Archive\Zip;
use Symfony\Component\Filesystem\Filesystem;

class UnpackStep
{
    /**
     * @var string
     */
    private $destinationDir;

    /**
     * @var string
     */
    private $source;

    /**
     * @param string $source
     * @param string $destinationDir
     */
    public function __construct($source, $destinationDir)
    {
        $this->source = $source;
        $this->destinationDir = rtrim($destinationDir, '/') . '/';
    }

    /**
     * @throws UpdateFailedException
     *
     * @return FinishResult|ValidResult
     */
    public function run(int $offset)
    {
        $fs = new Filesystem();
        $requestTime = time();

        try {
            $source = new Zip($this->source);
            $count = $source->count();
            $source->seek($offset);
        } catch (\Exception $e) {
            @unlink($this->source);

            throw new UpdateFailedException(sprintf('Could not open update package:<br>%s', $e->getMessage()), 0, $e);
        }

        /** @var ZipEntry $entry */
        while (list($position, $entry) = $source->each()) {
            $name = $entry->getName();
            $targetName = $this->destinationDir . $name;

            if (!$entry->isDir()) {
                $fs->dumpFile($targetName, $entry->getContents());
            }

            if (time() - $requestTime >= 20 || ($position + 1) % 1000 === 0) {
                $source->close();

                return new ValidResult($position + 1, $count);
            }
        }

        $source->close();
        unlink($this->source);

        return new FinishResult($count, $count);
    }
}
