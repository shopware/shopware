<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory
 */
class TwigVariableParserFactoryTest extends TestCase
{
    public function testGetParser(): void
    {
        $factory = new TwigVariableParserFactory();
        $twig = new Environment(new ArrayLoader([]));

        static::assertInstanceOf(TwigVariableParser::class, $factory->getParser($twig));
    }
}
