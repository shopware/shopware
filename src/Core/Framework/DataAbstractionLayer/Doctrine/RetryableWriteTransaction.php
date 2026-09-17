<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RetryableWriteTransaction
{
    /**
     * @var \WeakMap<Connection, self>|null
     */
    private static ?\WeakMap $scopes = null;

    // Keep this boundary for the whole transaction, including writes using a fresh WriteContext.
    private bool $callbacksStarted = false;

    private function __construct()
    {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(Connection): TReturn $closure
     *
     * @return TReturn
     */
    public static function retryable(Connection $connection, \Closure $closure)
    {
        $scopes = self::$scopes ??= new \WeakMap();
        if (isset($scopes[$connection])) {
            // Only the owner may retry. Nested writes share its callback boundary.
            return RetryableTransaction::transactional($connection, $closure);
        }

        $scope = new self();
        $scopes[$connection] = $scope;

        try {
            return RetryableTransaction::retryableWithPredicate(
                $connection,
                $closure,
                static fn (): bool => !$scope->callbacksStarted,
            );
        } finally {
            unset($scopes[$connection]);
        }
    }

    public static function preventRetries(Connection $connection): void
    {
        if (isset(self::$scopes[$connection])) {
            self::$scopes[$connection]->callbacksStarted = true;
        }
    }
}
