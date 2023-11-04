<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineNotFoundException extends ShopwareHttpException
{
    public function __construct(string $stateMachineName)
    {
        parent::__construct(
            'The StateMachine named "{{ name }}" was not found.',
            ['name' => $stateMachineName]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__STATE_MACHINE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
