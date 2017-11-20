<?php

class Util
{
    public static function getBundleName(string $table)
    {
        $tmp = explode('_', $table);
        return ucfirst(array_shift($tmp));
    }

    public static function buildDomainPlural(string $domainName)
    {
        $lastChar = substr($domainName, strlen($domainName) - 1, 1);

        switch (true) {
            case ($domainName === 'categoryTree'):
                return 'categoryTree';
            case ($domainName === 'productTree'):
                return 'productTree';
            case ($domainName === 'orderAddress'):
                return 'orderAddresses';
            case ($domainName === 'tax'):
                return 'taxes';
            case ($domainName === 'address'):
                return 'addresses';
            case ($domainName === 'customerAddress'):
                return 'customerAddresses';
            case ($domainName === 'productMedias'):
            case ($domainName === 'productMedia'):
                return 'productMedia';
            case ($domainName === 'orderDeliverys'):
                return 'orderDeliveries';
            case ($domainName === 'media'):
                return 'media';
            case ($domainName === 'mediaAlbums'):
            case ($domainName === 'mediaAlbum'):
                return 'mediaAlbum';
            case ($lastChar === 'y'):
                return substr($domainName, 0, strlen($domainName) - 1) . 'ies';
            case ($lastChar === 's'):
                return $domainName;
            default:
                return $domainName . 's';
        }
    }

    public static function getTableDomainName(string $table)
    {
        return self::snakeCaseToCamelCase($table);
    }

    public static function createPropertyName(string $table, string $name)
    {
        return self::snakeCaseToCamelCase(
            self::removeTableInName($table, $name)
        );
    }

    public static function createAssociationPropertyName(string $table, string $columnName)
    {
        $tmp = str_replace('_uuid', '', $columnName);

        return self::getTableDomainName(
            self::removeTableInName($table, $tmp)
        );
    }

    public static function snakeCaseToCamelCase(string $string): string
    {
        $explode = explode('_', $string);
        $explode = array_map('ucfirst', $explode);

        return lcfirst(implode($explode));
    }

    public static function getPhpType(ColumnDefinition $definition): ?string
    {
        switch ($definition->type) {
            case 'IntField':
                return 'int';
            case 'DateField':
                return "\\DateTime";
            case 'FloatField':
                return 'float';
            case 'BoolField':
                return 'bool';
            case 'LongTextField':
            case 'StringField':
            default:
                return 'string';
        }
    }

    /**
     * @param string $table
     * @param string $name
     * @return mixed|string
     */
    protected static function removeTableInName(string $table, string $name)
    {
        $prefix = $table . '_';
        $tmp = $name;

        if (strpos($name, $prefix) !== false) {
            $tmp = str_replace($prefix, '', $name);
        }

        return $tmp;
    }
}