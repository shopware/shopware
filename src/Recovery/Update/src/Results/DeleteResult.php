<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Results;

class DeleteResult
{
    /**
     * @var bool
     */
    private $isReady;

    /**
     * @var int
     */
    private $fileCount;

    /**
     * @param int $fileCount
     */
    public function __construct($fileCount = 0)
    {
        $this->isReady = false;
        $this->fileCount = $fileCount;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return __CLASS__;
    }

    /**
     * @return bool
     */
    public function getIsReady()
    {
        return $this->isReady;
    }

    /**
     * @return int
     */
    public function getFileCount()
    {
        return $this->fileCount;
    }

    /**
     * sets $this->isReady to "true"
     */
    public function setReady()
    {
        $this->isReady = true;
    }

    /**
     * Counts a $this->fileCount high
     */
    public function countUp()
    {
        ++$this->fileCount;
    }
}
