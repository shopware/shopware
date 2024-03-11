<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
abstract class AbstractResponse extends Struct
{
    final public function __construct()
    {
    }

    abstract public function validate(string $transactionId): void;

    /**
     * @param array<string, mixed> $data
     */
    public static function create(?string $transactionId, array $data): self
    {
        $response = new static();
        $response->assign($data);
        if ($transactionId) {
            $response->validate($transactionId);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options)
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }
}
