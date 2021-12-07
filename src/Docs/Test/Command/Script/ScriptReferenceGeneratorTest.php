<?php declare(strict_types=1);

namespace Command\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Docs\Command\Script\ScriptReferenceGenerator;
use Shopware\Docs\Command\Script\ScriptReferenceGeneratorCommand;

/**
 * @internal
 */
class ScriptReferenceGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGeneratedDocumentsAreRecent(): void
    {
        $generators = $this->getGenerators();

        foreach ($generators as $generator) {
            foreach ($generator->generate() as $filename => $content) {
                static::assertEquals(
                    $content,
                    file_get_contents($filename),
                    <<<MSG
The app scripting reference documentation is not up to date.
Please regenerate the documentation by running `composer dump-autoload --optimize` & `bin/console docs:generate-scripting-reference`.
Also ensure that the copied files in the publicly accessible gitbook @ `https://github.com/shopware/docs` are also updated!'
MSG
                );
            }
        }
    }

    /**
     * Ugly hack as the container does not expose all services with a specific tag
     *
     * @return iterable|ScriptReferenceGenerator[]
     */
    private function getGenerators(): iterable
    {
        $command = $this->getContainer()->get(ScriptReferenceGeneratorCommand::class);

        $reflection = new \ReflectionClass($command);

        $property = $reflection->getProperty('generators');
        $property->setAccessible(true);
        /** @var iterable|ScriptReferenceGenerator[] $generators */
        $generators = $property->getValue($command);

        return $generators;
    }
}
