<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Command;

use Shopware\Core\Framework\Changelog\ChangelogDefinition;
use Shopware\Core\Framework\Changelog\Processor\ChangelogGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class ChangelogCreateCommand extends Command
{
    protected static $defaultName = 'changelog:create';

    /**
     * @var ChangelogGenerator
     */
    private $generator;

    public function __construct(ChangelogGenerator $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a changelog markdown file in `/changelog/_unreleased`')
            ->addArgument('title', InputArgument::OPTIONAL, 'A short meaningful title of the change.')
            ->addArgument('issue', InputArgument::OPTIONAL, 'The corresponding Jira ticket key. Can be the key of a single ticket or the key of an epic.')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'The date in `YYYY-MM-DD` format which indicates the creation date of the change. Default is current date.')
            ->addOption('flag', null, InputOption::VALUE_OPTIONAL, 'Feature Flag ID')
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'The author of code changes')
            ->addOption('author-email', null, InputOption::VALUE_OPTIONAL, 'The author email of code changes')
            ->addOption('author-github', null, InputOption::VALUE_OPTIONAL, 'The author email of code changes')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Use the --dry-run argument to preview the changelog content and prevent actually writing to file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $IOHelper = new SymfonyStyle($input, $output);
        $IOHelper->title('Create a changelog markdown file');

        $default = $this->getDefaultData();
        $title = $input->getArgument('title')
            ?? $IOHelper->ask('A short meaningful title of your change', null, function ($title) {
                if (!$title) {
                    throw new \RuntimeException('Title is required in changelog file');
                }

                return $title;
            });
        $issue = $input->getArgument('issue')
            ?? $IOHelper->ask('The corresponding Jira ticket ID', null, function ($issue) {
                if (!$issue) {
                    throw new \RuntimeException('Jira ticket ID is required in changelog file');
                }

                return $issue;
            });
        $date = $input->getOption('date')
            ?? $IOHelper->ask('The date in `YYYY-MM-DD` format which the change will be applied', $default['date'], function ($date) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new \RuntimeException('The date has to follow the format: YYYY-MM-DD');
                }

                return $date;
            });
        $flag = $input->getOption('flag') ?? $IOHelper->ask('Feature Flag ID');
        $author = $input->getOption('author') ?? $IOHelper->ask('The author of code changes', $default['author']);
        $authorEmail = $input->getOption('author-email') ?? $IOHelper->ask('The author email of code changes', $default['authorEmail']);
        $authorGithub = $input->getOption('author-github') ?? $IOHelper->ask('The author Github account', $default['authorGithub']);

        $template = (new ChangelogDefinition())
            ->setTitle($title)
            ->setIssue($issue)
            ->setFlag($flag)
            ->setAuthor($author)
            ->setAuthorEmail($authorEmail)
            ->setAuthorGitHub($authorGithub);

        $IOHelper->section('Generating: ');
        $target = $this->generator->generate($template, $date, $input->getOption('dry-run'));

        $IOHelper->newLine();
        $IOHelper->success('The changelog was generated successfully');
        $IOHelper->note($target);

        return self::SUCCESS;
    }

    private function getDefaultData(): array
    {
        $process = new Process(['git', 'config', 'user.name']);
        $process->run();
        $gitUser = trim($process->getOutput());

        $process = new Process(['git', 'config', 'user.email']);
        $process->run();
        $gitEmail = trim($process->getOutput());

        return [
            'date' => (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'),
            'author' => $gitUser,
            'authorEmail' => $gitEmail,
            'authorGithub' => $gitUser,
        ];
    }
}
