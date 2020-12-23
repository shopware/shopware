<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppPrinter
{
    private const PRIVILEGE_TO_HUMAN_READABLE = [
        AclRoleDefinition::PRIVILEGE_READ => 'read',
        AclRoleDefinition::PRIVILEGE_CREATE => 'write',
        AclRoleDefinition::PRIVILEGE_UPDATE => 'write',
        AclRoleDefinition::PRIVILEGE_DELETE => 'delete',
    ];

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    public function __construct(EntityRepositoryInterface $appRepository)
    {
        $this->appRepository = $appRepository;
    }

    public function printInstalledApps(ShopwareStyle $io, Context $context): void
    {
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $context)->getEntities();

        if (empty($apps->getElements())) {
            return;
        }

        $appTable = [];

        foreach ($apps as $app) {
            $appTable[] = [
                $app->getName(),
                $app->getLabel(),
                $app->getVersion(),
                $app->getAuthor(),
            ];
        }

        $io->title('Installed apps');
        $io->table(
            ['App', 'Label', 'Version', 'Author'],
            $appTable
        );
    }

    /**
     * @psalm-param list<array{manifest: Manifest, exception: \Exception}> $fails
     */
    public function printIncompleteInstallations(ShopwareStyle $io, array $fails): void
    {
        if (empty($fails)) {
            return;
        }

        $appTable = [];

        foreach ($fails as $fail) {
            $appTable[] = [
                $fail['manifest']->getMetadata()->getName(),
                $fail['exception']->getMessage(),
            ];
        }

        $io->title('Incomplete installations');
        $io->table(
            ['App', 'Reason'],
            $appTable
        );
    }

    public function printPermissions(Manifest $app, ShopwareStyle $io, bool $install): void
    {
        $io->caution(
            sprintf(
                'App "%s" should be %s but requires following permissions:',
                $app->getMetadata()->getName(),
                $install ? 'installed' : 'updated'
            )
        );

        $this->printPermissionTable($io, $this->reducePermissions($app));
    }

    private function reducePermissions(Manifest $app): array
    {
        $permissions = [];
        foreach ($app->getPermissions()->getPermissions() as $resource => $privileges) {
            foreach ($privileges as $privilege) {
                $permissions[$resource][] = self::PRIVILEGE_TO_HUMAN_READABLE[$privilege];
            }
        }

        return $permissions;
    }

    private function printPermissionTable(ShopwareStyle $io, array $permissions): void
    {
        $permissionTable = [];
        foreach ($permissions as $resource => $privileges) {
            $permissionTable[] = [
                $resource,
                implode(', ', array_unique($privileges)),
            ];
        }

        $io->table(
            ['Resource', 'Privileges'],
            $permissionTable
        );
    }
}
