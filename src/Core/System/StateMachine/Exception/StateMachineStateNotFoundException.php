<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineStateNotFoundException extends ShopwareHttpException
{
    public function __construct(
        string $stateMachineName,
        string $technicalPlaceName
    ) {
        parent::__construct(
            'The place "{{ place }}" for state machine named "{{ stateMachine }}" was not found.',
            [
                'place' => $technicalPlaceName,
                'stateMachine' => $stateMachineName,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__STATE_MACHINE_STATE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
