<?php declare(strict_types=1);

namespace Shopware\Search;

class QuerySelection
{
    /**
     * @var string[]
     */
    protected $fields;

    /**
     * @var string
     */
    protected $root;

    /**
     * @var QuerySelection[]
     */
    private $filtered = [];

    public function __construct(array $fields, string $root)
    {
        $this->fields = $fields;
        $this->root = $root;
    }

    public static function createFromCriteria(Criteria $criteria, string $prefix, array $mapping): QuerySelection
    {
        $fields = [];

        foreach ($criteria->getFields() as $field) {
            if (strpos($field, '.') === false) {
                $field = $prefix . '.' . $field;
            }
            if (!array_key_exists($field, $mapping)) {
                continue;
            }

            $fields[$field] = $mapping[$field];
        }

        return new self($fields, $prefix);
    }

    public static function createFromNestedFields(array $fields, string $root): QuerySelection
    {
        $mapped = self::mapNestedFields($fields, $root);

        return new self($mapped, $root);
    }

    public function filter(string $prefix): ?QuerySelection
    {
        if (array_key_exists($prefix, $this->filtered)) {
            return $this->filtered[$prefix];
        }

        $fields = $this->filterFields($prefix);

        if (empty($fields)) {
            return $this->filtered[$prefix] = null;
        }

        return $this->filtered[$prefix] = new self(
            $fields,
            $this->implodeRoot($prefix)
        );
    }

    public function hasField(string $field)
    {
        if (array_key_exists($field, $this->fields)) {
            return true;
        }

        if (strpos($field, '.') === false) {
            $field = $this->getRoot() . '.' . $field;
        }

        return array_key_exists($field, $this->fields);
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $field): string
    {
        if (strpos($field, '.') === false) {
            $field = $this->getRoot() . '.' . $field;
        }

        if (array_key_exists($field, $this->fields)) {
            return $this->fields[$field];
        }

        return $field;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function getRootEscaped(): string
    {
        return '`' . $this->root . '`';
    }

    public function buildSelect(): array
    {
        $select = [];
        foreach ($this->getFields() as $name) {
            if (strpos($name, '_sub_select_') !== false) {
                continue;
            }
            $select[] = self::escapeFieldSelect($name);
        }

        return $select;
    }

    public function getFieldEscaped(string $field): string
    {
        return self::escapeField(
            $this->getField($field)
        );
    }

    public static function escapeFieldSelect(string $field): string
    {
        return  self::escapeField($field) . ' as ' . self::escape($field);
    }

    public static function escape(string $string): string
    {
        return '`' . $string . '`';
    }

    private static function escapeField(string $field): string
    {
        $table = explode('.', $field);
        $fieldName = array_pop($table);
        $table = self::implode($table);

        return self::escape($table) . '.' . self::escape($fieldName);
    }

    private static function prefix($field, string $prefix)
    {
        return self::implode([$prefix, $field]);
    }

    private static function implode(array $parts)
    {
        return implode('.', array_filter($parts));
    }

    /**
     * @param string $prefix
     *
     * @return array|string[]
     */
    private function filterFields(string $prefix): array
    {
        $affected = array_filter(
            $this->fields,
            function (string $field) use ($prefix) {
                return strpos($field, $this->implodeRoot($prefix) . '.') === 0;
            }
        );

        return $affected;
    }

    private function implodeRoot(string $prefix): string
    {
        return implode('.', array_filter([$this->root, $prefix]));
    }

    private static function mapNestedFields(array $fields, string $root): array
    {
        $mapping = [];
        foreach ($fields as $key => $value) {
            $mappedKey = self::prefix($key, $root);

            if (is_array($value)) {
                $nested = self::mapNestedFields(
                    $value,
                    self::implode([$root, $key])
                );
                foreach ($nested as $nestedKey => $nestedValue) {
                    $mapping[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $mapping[$mappedKey] = self::prefix($value, $root);
        }

        return $mapping;
    }
}
