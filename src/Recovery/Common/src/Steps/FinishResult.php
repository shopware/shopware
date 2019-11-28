<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Steps;

class FinishResult
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $total;

    /**
     * @var array
     */
    private $args;

    /**
     * @param int   $offset
     * @param int   $total
     * @param array $args
     */
    public function __construct($offset, $total, $args = [])
    {
        $this->offset = (int) $offset;
        $this->total = (int) $total;
        $this->args = $args;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }
}
