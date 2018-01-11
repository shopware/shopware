<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\Validation;

use Shopware\Framework\ShopwareException;

class RestrictDeleteViolationException extends \DomainException implements ShopwareException
{
    /**
     * @var RestrictDeleteViolation[]
     */
    protected $restrictions;

    public function __construct(array $restrictions)
    {
        $this->restrictions = $restrictions;
        parent::__construct('Delete of entities restricted', 400);
    }

    /**
     * @return RestrictDeleteViolation[]
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }
}
