<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineInvalidStateFieldException extends ShopwareHttpException
{
    public function __construct(string $fieldName)
    {
        parent::__construct(
            'Field "{{ fieldName }}" does not exists or isn\'t of type StateMachineStateField.',
            [
                'fieldName' => $fieldName,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__STATE_MACHINE_INVALID_STATE_FIELD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
