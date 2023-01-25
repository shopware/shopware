<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppPrinter
{
    private const PRIVILEGE_TO_HUMAN_READABLE = [
        AclRoleDefinition::PRIVILEGE_READ => 'read',
        AclRoleDefinition::PRIVILEGE_CREATE => 'write',
        AclRoleDefinition::PRIVILEGE_UPDATE => 'write',
        AclRoleDefinition::PRIVILEGE_DELETE => 'delete',
    ];

    public function __construct(private readonly EntityRepository $appRepository)
    {
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
     * @param list<array{manifest: Manifest, exception: \Exception}> $fails
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

    public function printPermissions(Manifest $manifest, ShopwareStyle $io, bool $install): void
    {
        $permissions = $manifest->getPermissions();

        if (!$permissions) {
            return;
        }

        $io->caution(
            sprintf(
                'App "%s" should be %s but requires the following permissions:',
                $manifest->getMetadata()->getName(),
                $install ? 'installed' : 'updated'
            )
        );

        $this->printPermissionTable($io, $permissions);
    }

    /**
     * @throws UserAbortedCommandException
     */
    public function checkHosts(Manifest $manifest, ShopwareStyle $io): void
    {
        $hosts = $manifest->getAllHosts();
        if (empty($hosts)) {
            return;
        }

        $this->printHosts($manifest, $hosts, $io, true);

        if (!$io->confirm(
            'Do you consent with data being shared or transferred to the domains listed above?',
            false
        )) {
            throw new UserAbortedCommandException();
        }
    }

    private function printHosts(Manifest $app, array $hosts, ShopwareStyle $io, bool $install): void
    {
        $io->caution(
            sprintf(
                'App "%s" should be %s but requires communication with the following hosts:',
                $app->getMetadata()->getName(),
                $install ? 'installed' : 'updated'
            )
        );

        $data = [];
        foreach ($hosts as $host) {
            $data[] = [$host];
        }

        $io->table(
            ['Domain'],
            $data
        );
    }

    private function printPermissionTable(ShopwareStyle $io, Permissions $permissions): void
    {
        $permissionTable = [];
        foreach ($this->reducePermissions($permissions) as $resource => $privileges) {
            $permissionTable[] = [
                $resource,
                implode(', ', array_unique($privileges)),
            ];
        }
        foreach ($permissions->getAdditionalPrivileges() as $additionalPrivilege) {
            $permissionTable[] = [
                '',
                $additionalPrivilege,
            ];
        }

        $io->table(
            ['Resource', 'Privileges'],
            $permissionTable
        );
    }

    private function reducePermissions(Permissions $permissions): array
    {
        $reduced = [];
        foreach ($permissions->getPermissions() as $resource => $privileges) {
            foreach ($privileges as $privilege) {
                $reduced[$resource][] = self::PRIVILEGE_TO_HUMAN_READABLE[$privilege];
            }
        }

        return $reduced;
    }
}
