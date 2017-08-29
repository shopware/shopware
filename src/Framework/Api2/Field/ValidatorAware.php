<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Field3;

use Symfony\Component\Validator\Validator\ValidatorInterface;

interface ValidatorAware
{
    public function setValidator(ValidatorInterface $validator);
}