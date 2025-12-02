<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Response;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
abstract class AbstractResponse extends Struct
{
    /**
     * This message is not used on successful outcomes.
     * The message should be provided on failure.
     * Payment will fail if provided.
     */
    protected ?string $message = null;

    final public function __construct()
    {
    }

    public function getErrorMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): static
    {
        /** @phpstan-ignore new.staticInAbstractClassStaticMethod (the usage of "new static" is explicitly wanted) */
        $response = new static();
        $response->assign($data);

        return $response;
    }
}
