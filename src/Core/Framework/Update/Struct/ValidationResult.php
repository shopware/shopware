<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('system-settings')]
class ValidationResult extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $result;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array<mixed>
     */
    protected $vars;

    /**
     * @param array<mixed> $vars
     */
    public function __construct(
        string $name,
        bool $result,
        string $message,
        array $vars = []
    ) {
        $this->name = $name;
        $this->result = $result;
        $this->message = $message;
        $this->vars = $vars;
    }

    public function getApiAlias(): string
    {
        return 'update_api_validation_result';
    }
}
