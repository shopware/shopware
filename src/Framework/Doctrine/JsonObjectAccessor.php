<?php

namespace Shopware\Framework\Doctrine;

class JsonObjectAccessor
{
    const ENGINE_MYSQL = 'mysql';
    const ENGINE_MARIADB = 'mariadb';

    public static $engine = self::ENGINE_MYSQL;

    public static function parse(string $field, string $path)
    {
        if (self::$engine === self::ENGINE_MARIADB) {
            return sprintf('JSON_VALUE(%s, "%s")', $field, $path);
        }

        if (self::$engine === self::ENGINE_MYSQL) {
            return sprintf('%s->"%s"', $field, $path);
        }

        throw new \RuntimeException('Unsupported database engine detected.');
    }
}