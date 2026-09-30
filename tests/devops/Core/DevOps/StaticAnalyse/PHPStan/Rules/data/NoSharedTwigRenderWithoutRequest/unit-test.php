<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\SharedTwigFixture;

use PHPUnit\Framework\TestCase;
use Shopware\Tests\Integration\SharedTwigFixture\TwigContainer;

/**
 * Outside the enabled namespaces: the unit suite boots no kernel, so it has no shared environment.
 */
class RendersOutsideTheIntegrationSuiteTest extends TestCase
{
    public function testRender(TwigContainer $container): void
    {
        $container->get('twig')->render('a.html.twig');
    }
}
