<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ListField extends Field
{
    public const TYPE = 'list';

    /**
     * @param class-string<DalField>|null $fieldType
     */
    public function __construct(
        public ?string $fieldType = null,
        public bool|array $api = false,
        public bool $translated = false,
        public ?string $column = null,
    ) {
        parent::__construct(type: self::TYPE, translated: $translated, api: $api, column: $column);
    }
}
