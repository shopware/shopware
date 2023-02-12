<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigVariableParserFactoryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testgetParser(): void
    {
        $factory = $this->getContainer()->get(TwigVariableParserFactory::class);
        $twig = new Environment(new ArrayLoader([]));

        static::assertInstanceOf(TwigVariableParser::class, $factory->getParser($twig));
    }
}
