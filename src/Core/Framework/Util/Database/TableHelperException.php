<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class TableHelperException extends UtilException
{
    public function __construct(string $executedAction, \Throwable $previousException)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DB_TABLE_HELPER_EXCEPTION,
            'Could not execute "{{ executedAction }}". Reason: {{ message }}',
            ['executedAction' => $executedAction, 'message' => $previousException->getMessage()],
            $previousException,
        );
    }
}
