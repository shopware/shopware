<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Symfony\Component\Validator\ConstraintViolationInterface;

class Usage extends StorefrontController
{
    public function useConstraintViolation(ConstraintViolationInterface $violation): string
    {
        $violation->getCode();

        return $violation->getMessage();
    }
}
