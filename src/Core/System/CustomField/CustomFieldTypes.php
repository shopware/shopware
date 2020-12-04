<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

final class CustomFieldTypes
{
    public const BOOL = 'bool';
    public const COLORPICKER = 'colorpicker';
    public const DATETIME = 'datetime';
    public const ENTITY = 'entity';
    public const FLOAT = 'float';
    public const INT = 'int';
    public const JSON = 'json';
    public const HTML = 'html';
    public const MEDIA = 'media';
    public const SELECT = 'select';
    public const SWITCH = 'switch';
    public const TEXT = 'text';

    private function __construct()
    {
    }
}
