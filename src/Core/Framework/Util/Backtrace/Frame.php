<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Backtrace;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class Frame
{
    public function __construct(
        public ?string $class,
        public ?string $function,
    ) {
    }

    /**
     * @return array{class: string|null, function: string|null}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'function' => $this->function,
        ];
    }
}
