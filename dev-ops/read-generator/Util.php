<?php

namespace ReadGenerator;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class Util
{
    const TO_ONE = ['N:1', '1:1'];
    const TO_MANY = ['N:N', '1:N'];

    const MANY_TO_ONE = 'N:1';
    const ONE_TO_MANY = '1:N';
    const MANY_TO_MANY = 'N:N';
    const ONE_TO_ONE = '1:1';

    public static function snakeCaseToCamelCase(string $string): string
    {
        $last = substr($string, strlen($string) - 3);
        if ($last === '_ro') {
            $string = substr($string, 0, strlen($string) - 3);
        }

        $explode = explode('_', $string);
        $explode = array_map('ucfirst', $explode);

        return lcfirst(implode($explode));
    }

    public static function getType(Column $column): string
    {
        switch ($column->getType()->getName()) {
            case Type::TARRAY:
            case Type::SIMPLE_ARRAY:
            case Type::JSON_ARRAY:
            case Type::OBJECT:
                return 'array';

            case Type::BOOLEAN:
                return 'bool';

            case Type::STRING:
            case Type::FLOAT:
                return $column->getType()->getName();

            case Type::BLOB:
            case Type::TEXT:
                return 'string';

            case Type::INTEGER:
            case Type::SMALLINT:
            case Type::BIGINT:
            case Type::BINARY:
            case Type::GUID:
                return 'int';

            case Type::DATETIME:
            case Type::DATETIMETZ:
            case Type::DATE:
            case Type::TIME:
                return '\DateTime';

            case Type::DECIMAL:
                return 'float';

            default:
                return 'string';
        }
    }

    public static function getPlural(string $name): ?string
    {
        $lastChar = substr($name, strlen($name) - 1, 1);

        $name = ucfirst($name);

        switch (true) {
            case ($name === 'CategoryTree'):
                return 'CategoryTree';
            case ($name === 'Tax'):
                return 'Taxes';
            case ($name === 'Album'):
                return 'Album';
            case ($name === 'OrderAddress'):
                return 'OrderAddresses';
            case ($name === 'ShippingAddress'):
                return 'ShippingAddresses';
            case ($name === 'BillingAddress'):
                return 'BillingAddresses';
            case ($name === 'BlockedCustomerGroups'):
                return 'BlockedCustomerGroups';
            case ($name === 'CustomerGroups'):
                return 'CustomerGroups';
            case ($name === 'Information'):
                return 'Information';
            case ($name === 'Media'):
                return 'Media';
            case ($name === 'Address'):
                return 'Addresses';
            case ($name === 'CustomerAddress'):
                return 'CustomerAddresses';
            case ($name == 'Holiday'):
                return 'Holidays';
            case ($lastChar === 'y'):
                return substr($name, 0, strlen($name) - 1) . 'ies';
            default:
                return $name . 's';
        }
    }

    public static function isToOne($type)
    {
        return in_array($type, self::TO_ONE, true);
    }

    public static function isToMany($type)
    {
        return in_array($type, self::TO_MANY, true);
    }

    public static function getAssociationsForBasicLoader($table, $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === true
                && ($association['load_by_association_loader'] === true || self::isToMany($association['type']));
        });
    }

    public static function getAssociationsForDetailStruct($table, $config): array
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false;
        });
    }

    public static function getAssociationsForDetailLoader($table, $config): array
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false
                && ($association['load_by_association_loader'] === true || self::isToMany($association['type']));
        });
    }


    public static function getAssociationsForBasicQuery($table, $config)
    {
        return array_filter(
            $config['associations'],
            function ($association) {
                return $association['in_basic'] === true
                    && ($association['load_by_association_loader'] === false || $association['type'] === 'N:N');
            }
        );
    }

    public static function getAssociationPropertyName($association): string
    {
        if (!empty($association['property'])) {
            return lcfirst($association['property']);
        }

        return lcfirst(
            self::snakeCaseToCamelCase($association['table'])
        );
    }

    public static function getPropertyName(string $table, string $columnName): string
    {
        if (strpos($columnName, $table.'_') === 0) {
            $columnName = str_replace($table.'_', '', $columnName);
        }

        return self::snakeCaseToCamelCase($columnName);
    }

    public static function getAssociationsForBasicStruct($table, $config)
    {
        return array_filter(
            $config['associations'],
            function ($association) {
                return $association['in_basic'] === true;
            }
        );
    }

    public static function getAssociationsForDetailQuery(string $table, array $config)
    {
        return array_filter($config['associations'], function($association) {
            return $association['in_basic'] === false
                && ($association['load_by_association_loader'] === false || $association['type'] === 'N:N');
        });
    }

}