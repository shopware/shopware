<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;

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

    public static function output(): ConsoleOutput
    {
        $output = new ConsoleOutput(decorated: false);

        $stream = fopen('php://memory', 'w', false);
        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory stream.');
        }

        $reflection = new \ReflectionProperty(StreamOutput::class, 'stream');
        $reflection->setValue($output, $stream);

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
