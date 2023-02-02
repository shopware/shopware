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

    public static function create(?string $transactionId, array $data): self
    {
        $response = new static();
        $response->assign($data);
        if ($transactionId) {
            $response->validate($transactionId);
        }

        return $response;
    }
}
