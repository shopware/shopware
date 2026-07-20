<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Password extends Field
{
    public const TYPE = 'password';

    /**
     * @param array<string, mixed> $hashOptions
     */
    public function __construct(
        public ?string $algorithm = \PASSWORD_DEFAULT,
        public array $hashOptions = [],
        public ?string $for = null,
        public bool|array $api = false,
        public ?string $column = null,
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }
}
