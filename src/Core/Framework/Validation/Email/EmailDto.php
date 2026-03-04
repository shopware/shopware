<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Email;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('framework')]
class EmailDto extends Struct
{
    protected bool $isValid = false;

    public function __construct(
        #[NotBlank]
        #[Email]
        public readonly string $email
    ) {
    }

    public function setIsValid(bool $isValid): void
    {
        $this->isValid = $isValid;
    }
}
