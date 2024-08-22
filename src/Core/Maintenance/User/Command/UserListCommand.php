<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\User\Command;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:list',
    description: 'List current users',
)]
#[Package('core')]
class UserListCommand extends Command
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Return users as json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->userRepository->search($criteria, $context);

        if ($input->getOption('json')) {
            $output->write(json_encode($this->mapUsersToJson($result->getEntities()), \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($result->getTotal() === 0) {
            $io->warning('There are no users.');

            return self::SUCCESS;
        }

        $io->table(
            ['Id', 'E-mail', 'Username', 'Name', 'Active', 'Roles', 'Created At'],
            $this->mapUsersToConsole($result->getEntities())
        );

        return self::SUCCESS;
    }

    /**
     * @return list<array{
     *     id: string,
     *     'email': string,
     *     'active': string,
     *     'username': string,
     *     'name': string,
     *     'roles': array<string>,
     *     'date_created': string
     * }>
     */
    private function mapUsersToJson(UserCollection $collection): array
    {
        return array_values($collection->map(function (UserEntity $user) {
            return [
                ...$this->mapUser($user),
                'active' => $user->getActive(),
                'roles' => $this->roles($user),
                'created' => $user->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? '',
            ];
        }));
    }

    /**
     * @return list<array{
     *     id: string,
     *     'email': string,
     *     'username': string,
     *     'name': string,
     *     'active': string,
     *     'roles': string,
     *     'date_created': string
     * }>
     */
    private function mapUsersToConsole(UserCollection $collection): array
    {
        return array_values($collection->map(function (UserEntity $user) {
            return [
                ...$this->mapUser($user),
                'active' => $user->getActive(),
                'roles' => implode(',', $this->roles($user)),
                'created' => $user->getCreatedAt()?->format('M j, y, g:i a') ?? '',
            ];
        }));
    }

    /**
     * @return array{
     *     id: string,
     *     'email': string,
     *     'username': string,
     *     'name': string,
     * }
     */
    private function mapUser(UserEntity $entity): array
    {
        return [
            'id' => $entity->getId(),
            'email' => $entity->getEmail(),
            'username' => $entity->getUsername(),
            'name' => $entity->getFirstName() . ' ' . $entity->getLastName(),
        ];
    }

    /**
     * @return list<string>
     */
    private function roles(UserEntity $entity): array
    {
        if ($entity->isAdmin()) {
            return ['admin'];
        }

        return array_values($entity->getAclRoles()->map(fn (AclRoleEntity $role) => $role->getName()));
    }
}
