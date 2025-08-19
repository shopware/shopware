<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Privileges\Privileges;
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
                'privileges' => $this->privileges->getRequestedPrivilegesForAllApps(),
            ]);
        });
    }

    #[Route(
        path: '/api/app-system/{appName}/privileges',
        name: 'api.app_system.privileges.accept',
        defaults: ['_acl' => ['system.plugin_maintain']],
        methods: [Request::METHOD_PATCH]
    )]
    public function updatePrivileges(Request $request, Context $context, string $appName): Response
    {
        $this->assertHasUserId($context);

        $requestAsArray = $request->toArray();
        $privilegesToAccept = $requestAsArray['accept'] ?? [];
        $privilegesToRevoke = $requestAsArray['revoke'] ?? [];
        if (!\is_array($privilegesToAccept) || !\is_array($privilegesToRevoke)) {
            throw AppException::invalidPrivileges();
        }

        $privilegesToAccept = array_values(array_filter($privilegesToAccept, is_string(...)));
        $privilegesToRevoke = array_values(array_filter($privilegesToRevoke, is_string(...)));

        $context->scope(Context::SYSTEM_SCOPE, function () use ($appName, $privilegesToAccept, $privilegesToRevoke, $context): void {
            $id = $this->fetchAppId($appName);

            try {
                $this->privileges->updatePrivileges($id, $privilegesToAccept, $privilegesToRevoke, $context);
            } catch (AppException $exception) {
                throw $exception;
            } catch (\Throwable) {
                // no-op
            }
        });

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/app-system/{appName}/privileges/accepted',
        name: 'api.app_system.privileges.accepted',
        methods: [Request::METHOD_GET]
    )]
    public function getAcceptedPrivileges(Context $context): JsonResponse
    {
        $source = $this->getSourceWithIntegration($context);
        $privileges = [];
        foreach ($source->getPermissions() as $permission) {
            $privileges[$permission] = true;
        }

        return new JsonResponse([
            'privileges' => $privileges,
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
