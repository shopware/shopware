<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineWithoutInitialStateException extends ShopwareHttpException
{
    public function __construct(string $stateMachineName)
    {
        parent::__construct(
            'The StateMachine named "{{ name }}" has no initial state.',
            ['name' => $stateMachineName]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__STATE_MACHINE_WITHOUT_INITIAL_STATE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
