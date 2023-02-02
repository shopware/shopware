<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\Validator\ConstraintViolationList;

interface ConstraintViolationExceptionInterface extends ShopwareException
{
    public function getViolations(): ConstraintViolationList;
}
