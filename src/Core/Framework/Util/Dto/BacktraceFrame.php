<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Dto;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class BacktraceFrame
{
    public function __construct(
        public ?string $class,
        public ?string $function,
    ) {
    }
}
