<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class ConfigurationNotFoundException extends ShopwareHttpException
{
    public function __construct(string $scope)
    {
        parent::__construct(
            'Configuration for scope "{{ scope }}" not found.',
            ['scope' => $scope]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__SCOPE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
