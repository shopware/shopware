<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services\Archive\Entry;

class Zip
{
    /**
     * @var int
     */
    protected $position;

    /**
     * @var \ZipArchive
     */
    protected $stream;

    /**
     * @var string
     */
    protected $name;

    public function __construct(\ZipArchive $stream, int $position)
    {
        $this->position = $position;
        $this->stream = $stream;
        $this->name = $stream->getNameIndex($position);
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->stream->getStream($this->name);
    }

    public function getContents()
    {
        return $this->stream->getFromIndex($this->position);
    }

    public function isDir(): bool
    {
        return mb_substr($this->name, -1) === '/';
    }

    public function isFile(): bool
    {
        return mb_substr($this->name, -1) !== '/';
    }

    public function getName(): string
    {
        $name = $this->name;
        if (mb_strpos($name, './') === 0) {
            $name = mb_substr($name, 2);
        }

        return $name;
    }
}
