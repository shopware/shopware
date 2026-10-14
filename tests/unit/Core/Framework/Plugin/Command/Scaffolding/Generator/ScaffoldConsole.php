<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Console input and output for scaffolding questions.
 *
 * The option value is returned for every option name. The answer is what the user types,
 * for example "y" or "n". Several answers are separated by newlines.
 *
 * @internal
 */
final class ScaffoldConsole
{
    public static function input(mixed $option, string $answer = ''): ArrayInput
    {
        return new ScaffoldConsoleInput($option, $answer);
    }

    public static function output(): ConsoleOutputInterface
    {
        $command = (new Command('scaffold:test'))->setCode(static fn (): int => Command::SUCCESS);
        $tester = new CommandTester($command);
        $tester->execute([], ['decorated' => false, 'capture_stderr_separately' => true]);

        $output = $tester->getOutput();
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('Expected console output with separate stderr capture.');
        }

        return $output;
    }
}

/**
 * @internal
 */
final class ScaffoldConsoleInput extends ArrayInput
{
    public function __construct(
        private readonly mixed $option,
        string $answer,
    ) {
        parent::__construct([]);

        $stream = fopen('php://memory', 'r+', false);
        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory stream.');
        }

        fwrite($stream, $answer . "\n");
        rewind($stream);

        $this->setStream($stream);
        $this->setInteractive(true);
    }

    public function getOption(string $name): mixed
    {
        return $this->option;
    }
}
