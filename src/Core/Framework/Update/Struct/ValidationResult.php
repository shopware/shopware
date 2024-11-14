<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('services-settings')]
class ValidationResult extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $result;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $message;

    /**
     * @var array<mixed>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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
