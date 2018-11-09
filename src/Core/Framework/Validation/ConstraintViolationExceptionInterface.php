<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

interface ConstraintViolationExceptionInterface extends ShopwareException
{
    public function getViolations(): ConstraintViolationListInterface;
}
