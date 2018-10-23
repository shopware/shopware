<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Type;

use Shopware\Core\Framework\Rule\Type\Field\Field;
use Shopware\Core\Framework\Struct\Struct;

class RuleTypeStruct extends Struct
{
    /** @var Field[] */
    protected $fields;

    /** @var string */
    protected $label;

    /** @var string */
    protected $type;

    /** @var Scope[] */
    protected $scopes;

    /**
     * @param Field[] $fields
     * @param Scope[] $scopes
     */
    public function __construct(string $label, string $type, array $scopes, array $fields)
    {
        $this->label = $label;
        $this->type = $type;
        $this->scopes = $scopes;
        $this->fields = $fields;
    }
}