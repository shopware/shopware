<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api\DTO;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class VerifyShop
{
    public function __construct(
        #[Assert\NotBlank]
        public string $runId,
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
