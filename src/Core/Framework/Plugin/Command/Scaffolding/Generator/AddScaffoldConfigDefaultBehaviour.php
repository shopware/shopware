<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 */
#[Package('framework')]
trait AddScaffoldConfigDefaultBehaviour
{
    protected bool $shouldAskCliQuestion = true;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);

            return;
        }

        if ($this->askCliQuestion($input, $output, self::CLI_QUESTION)) {
            $config->addOption(self::OPTION_NAME, true);
        }
    }

    /**
     * The question is written to the given output, so the command can put it into a section and clear it afterwards.
     */
    protected function askCliQuestion(InputInterface $input, OutputInterface $output, string $question): bool
    {
        if (!$this->shouldAskCliQuestion) {
            return false;
        }

        if (!$output instanceof ConsoleOutputInterface) {
            throw new \InvalidArgumentException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $helper = new QuestionHelper();
        $tempSection = $output->section();

        $tempSection->writeln(\sprintf('<options=bold>%s</>', self::OPTION_TITLE));
        $tempSection->writeln(self::OPTION_DESCRIPTION_LONG);
        $tempSection->writeln('');

        $confirmation = new ConfirmationQuestion(\sprintf('<fg=green>%s [Y/n]:</>', $question), true, '/^(y|j)/i');
        $answer = $helper->ask($input, $tempSection, $confirmation);
        $tempSection->clear();

        return (bool) $answer;
    }
}
