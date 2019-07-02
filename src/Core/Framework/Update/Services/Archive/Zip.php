<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services\Archive;

use Shopware\Core\Framework\Plugin\Util\ZipUtils;

class Zip extends Adapter
{
    /**
     * @var \ZipArchive
     */
    protected $stream;

    public function __construct(?string $fileName = null)
    {
        $this->stream = new \ZipArchive();

        if ($fileName !== null) {
            $this->stream = ZipUtils::openZip($fileName);

            $this->position = 0;
            $this->count = $this->stream->numFiles;
        }
    }

    public function current(): Entry\Zip
    {
        return new Entry\Zip($this->stream, $this->position);
    }

    /**
     * @return resource
     */
    public function getStream(string $name)
    {
        return $this->stream->getStream($name);
    }

    public function getContents(string $name)
    {
        return $this->stream->getFromName($name);
    }

    public function getEntry(int $position)
    {
        return $this->stream->statIndex($position);
    }

    public function close(): bool
    {
        return $this->stream->close();
    }
}
