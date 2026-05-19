<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MeasurementSystem\TwigExtension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;

/**
 * @internal
 */
class MeasurementConvertUnitTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = static::getContainer()->get('twig');
    }

    public function testConvertUnitFilterCanBeUsedInTwigTemplate(): void
    {
        $template = $this->twig->createTemplate('{{ 1000|sw_convert_unit("mm", "m") }}');

        static::assertSame('1 m', $template->render());
    }
}
