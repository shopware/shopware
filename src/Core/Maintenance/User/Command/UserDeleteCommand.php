<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\User\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'user:delete',
    description: 'Deletes a user by the given user id',
)]
#[Package('framework')]
class UserDeleteCommand extends Command
{
    public function __construct(private readonly EntityRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user_id', InputArgument::REQUIRED, 'User ID for the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $context = Context::createCLIContext();

        $userId = $input->getArgument('user_id');

        $findUserById = $this->userRepository->search(new Criteria([$userId]), $context)->first();
        if (!$findUserById) {
            $io->error(sprintf('User with the ID: "%s" does not exist.', $userId));
            return self::INVALID;
        }

        $askConfirmationQuestion = new ConfirmationQuestion(sprintf('Would you like to delete the user with the ID: "%s" ?', $userId), false);
        $askConfirmation = $io->askQuestion($askConfirmationQuestion);
        if ($askConfirmation) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): void {
                $this->userRepository->delete([['id' => $userId]], $context);
            });

            $message = \sprintf('User with the ID: "%s" successfully deleted.', $userId);
            $io->success($message);
        } else {
            $io->info('The process was canceled and the user was not deleted');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
