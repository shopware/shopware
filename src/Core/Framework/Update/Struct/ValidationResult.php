<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class ValidationResult extends Struct
{
    /**
     * @param array<mixed> $vars
     */
    public function __construct(
        protected string $name,
        protected bool $result,
        protected string $message,
        protected array $vars = []
    ) {
    }

    public function getApiAlias(): string
    {
        return 'update_api_validation_result';
    }
}
