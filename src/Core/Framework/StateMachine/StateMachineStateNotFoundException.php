<?php declare(strict_types=1);

namespace Shopware\Core\Framework\StateMachine;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StateMachineStateNotFoundException extends ShopwareHttpException
{
    public function __construct(string $stateMachineName, string $technicalPlaceName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The place "%s" for state machine named "%s" was not found.', $technicalPlaceName, $stateMachineName);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
