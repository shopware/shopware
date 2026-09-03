<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CloneProtectedException extends ShopwareHttpException
{
    public function __construct(string $entity, string $scope)
    {
        parent::__construct(
            'The entity "{{ entity }}" is clone protected for your scope "{{ scope }}".',
            [
                'entity' => $entity,
                'scope' => $scope,
            ],
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CLONE_PROTECTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
