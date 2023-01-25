<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ResponseFields
{
    /**
     * @var array|null
     */
    protected $includes;

    public function __construct(?array $includes)
    {
        $this->includes = $includes;
    }

    public function isAllowed(string $type, string $property): bool
    {
        if (!isset($this->includes[$type])) {
            return true;
        }

        return \in_array($property, $this->includes[$type], true);
    }

    public function hasNested(string $alias, string $prefix): bool
    {
        $fields = $this->includes[$alias] ?? [];

        $prefix .= '.';
        foreach ($fields as $property) {
            if (mb_strpos((string) $property, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
