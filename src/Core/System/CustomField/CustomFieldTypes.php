<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

final class CustomFieldTypes
{
    public const BOOL = 'bool';
    public const DATETIME = 'datetime';
    public const FLOAT = 'float';
    public const INT = 'int';
    public const JSON = 'json';
    public const TEXT = 'text';
    public const HTML = 'html';

    private function __construct()
    {
    }
}
