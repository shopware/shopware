<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
enum FieldType: string
{
    public const UUID = 'uuid';
    public const STRING = 'string';
    public const TEXT = 'text';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const JSON = 'json';
    public const DATETIME = 'datetime';
    public const DATE = 'date';
    public const DATE_INTERVAL = 'date-interval';
    public const TIME_ZONE = 'time-zone';
}
