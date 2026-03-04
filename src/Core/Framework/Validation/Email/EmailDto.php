<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Email;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Validator\Constraints as Assert;

#[Package('framework')]
class EmailDto extends Struct
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email
    ) {
    }
}
