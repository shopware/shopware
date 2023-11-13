<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
final class CustomFieldTypes
{
    public const BOOL = 'bool';
    public const CHECKBOX = 'checkbox';
    public const COLORPICKER = 'colorpicker';
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const ENTITY = 'entity';
    public const FLOAT = 'float';
    public const INT = 'int';
    public const JSON = 'json';
    public const NUMBER = 'number';
    public const PRICE = 'price';
    public const HTML = 'html';
    public const MEDIA = 'media';
    public const SELECT = 'select';
    public const SWITCH = 'switch';
    public const TEXT = 'text';

    private function __construct()
    {
    }
}
