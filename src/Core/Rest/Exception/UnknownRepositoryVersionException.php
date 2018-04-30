<?php declare(strict_types=1);

namespace Shopware\Rest\Exception;

use Shopware\Framework\ShopwareException;
use Throwable;

class UnknownRepositoryVersionException extends \Exception implements ShopwareException
{
    /**
     * @inheritDoc
     */
    public function __construct(string $entityName, int $version, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('There is no "v%d" version of the "%s" repository.', $version, $entityName);
        parent::__construct($message, $code, $previous);
    }

}