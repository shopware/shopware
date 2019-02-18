<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

class ArrayOfType extends Constraint
{
    public const INVALID_MESSAGE = 'This value "{{ value }}" should be of type {{ type }}.';
    public const INVALID_TYPE_MESSAGE = 'This value should be of type array.';

    /**
     * @var string
     */
    public $type;

    public function __construct(string $type)
    {
        parent::__construct();
        $this->type = $type;
    }
}
