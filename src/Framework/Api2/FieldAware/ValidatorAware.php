<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\FieldAware;

use Symfony\Component\Validator\Validator\ValidatorInterface;

interface ValidatorAware
{
    public function setValidator(ValidatorInterface $validator): void;
}