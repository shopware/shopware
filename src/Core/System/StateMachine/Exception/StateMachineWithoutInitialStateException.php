<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class StateMachineWithoutInitialStateException extends ShopwareHttpException
{
    protected $code = 'STATE-MACHINE-WITHOUT-INITIAL-STATE';

    public function __construct(string $stateMachineName, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The StateMachine named "%s" has no initial state.', $stateMachineName);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
