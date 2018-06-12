<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Read;

use Shopware\Core\Framework\ORM\Search\Criteria;

class ReadCriteria extends Criteria
{
    /**
     * @var string[]
     */
    protected $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
