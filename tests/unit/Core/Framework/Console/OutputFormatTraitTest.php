<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Console;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Console\OutputFormatTrait;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversTrait(OutputFormatTrait::class)]
class OutputFormatTraitTest extends TestCase
{
    #[DataProvider('formattedTableProvider')]
    public function testRenderFormattedTable(string $format, string $expected): void
    {
        $command = new class extends Command {
            use OutputFormatTrait;

            public function __construct()
            {
                parent::__construct('test:list');
            }

            protected function configure(): void
            {
                $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_MARKDOWN]);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_MARKDOWN]);
                \assert(\is_string($format));

                $this->renderFormattedTable(new SymfonyStyle($input, $output), $format, ['Name', 'Active'], [['Foo', 'Yes']]);

                return self::SUCCESS;
            }
        };

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => $format]);

        $commandTester->assertCommandIsSuccessful();
        static::assertSame($expected, preg_replace('/ +$/m', '', $commandTester->getDisplay()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formattedTableProvider(): iterable
    {
        yield 'table' => ['table', <<<'EOF'
             ------ --------
              Name   Active
             ------ --------
              Foo    Yes
             ------ --------


            EOF];

        yield 'markdown' => ['markdown', <<<'EOF'
            | Name | Active |
            |------|--------|
            | Foo  | Yes    |


            EOF];
    }
}
