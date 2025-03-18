<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class AppPrivilegeController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Privileges $privileges
    ) {
    }

    #[Route(
        path: '/api/app-system/privileges/requested',
        name: 'api.app_system.privileges.requested',
        defaults: ['_acl' => ['system.plugin_maintain']],
        methods: [Request::METHOD_GET]
    )]
    public function getRequestedPrivileges(Context $context): JsonResponse
    {
        $this->assertHasUserId($context);

        return $context->scope(Context::SYSTEM_SCOPE, function () {
            return new JsonResponse([
                'requestedPrivileges' => array_map(
                    fn (array $privileges) => Utils::makeCategorizedPermissions($privileges),
                    $this->privileges->getRequestedPrivilegesForAllApps()
                ),
            ]);
        });
    }

    #[Route(
        path: '/api/app-system/{appName}/privileges/accept',
        name: 'api.app_system.privileges.accept',
        defaults: ['_acl' => ['system.plugin_maintain']],
        methods: [Request::METHOD_POST]
    )]
    public function acceptPrivileges(Request $request, Context $context, string $appName): Response
    {
        $this->assertHasUserId($context);

        $privilegesToAccept = $request->toArray();
        $privilegesToAccept = array_values(array_filter($privilegesToAccept, is_string(...)));

        if (\count($privilegesToAccept) === 0 || \count($request->toArray()) !== \count($privilegesToAccept)) {
            throw AppException::invalidPrivileges();
        }

        $context->scope(Context::SYSTEM_SCOPE, function () use ($appName, $privilegesToAccept, $context): void {
            $id = $this->fetchAppId($appName);

            try {
                $this->privileges->acceptOnly($id, $privilegesToAccept, $context);
            } catch (\Throwable) {
                // no-op
            }
        });

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/app-system/privileges/accepted',
        name: 'api.app_system.privileges.accepted',
        methods: [Request::METHOD_GET]
    )]
    public function getAcceptedPrivileges(Context $context): JsonResponse
    {
        $source = $this->getSourceWithIntegration($context);

        return new JsonResponse([
            'acceptedPrivileges' => Utils::makeCategorizedPermissions($source->getPermissions()),
        ]);
    }

    private function fetchAppId(string $appName): string
    {
        $id = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM app WHERE name = ?', [$appName]);

        if (!$id) {
            throw AppException::notFoundByField($appName, 'name');
        }

        return $id;
    }

    private function getSource(Context $context): AdminApiSource
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw AppException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        return $source;
    }

    private function assertHasUserId(Context $context): void
    {
        $source = $this->getSource($context);

        if ($source->getUserId() === null) {
            throw AppException::missingUserInContextSource($source::class);
        }
    }

    private function getSourceWithIntegration(Context $context): AdminApiSource
    {
        $source = $this->getSource($context);

        $integrationId = $source->getIntegrationId();
        if (!$integrationId) {
            throw AppException::missingIntegration();
        }

        return $source;
    }
}
