<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

enum FieldType: string
{
    public const STRING = 'string';
    public const TEXT = 'text';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const DATETIME = 'datetime';
    public const UUID = 'uuid';
    public const AUTO_INCREMENT = 'auto-increment';
    public const JSON = 'json';
    public const DATE = 'date';
    public const DATE_INTERVAL = 'date-interval';
    public const TIME_ZONE = 'time-zone';

    public const EMAIL = 'email';
    public const LIST = 'list';
    public const PASSWORD = 'password';
    public const REMOTE_ADDRESS = 'remote-address';
}
