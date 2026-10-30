<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedBlockReferenceNode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedBlockReferenceNode::class)]
class DeprecatedBlockReferenceNodeTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::registerFeatures(['v6.8.0.0', 'TWIG_COMPILE_TIME_OPTIMIZATION']);
    }

    public function testActiveMajorSkipsOnlyDeprecatedInvocation(): void
    {
        static::assertSame('BEFOREAFTER', $this->render());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInactiveMajorPreservesSurroundingOutput(): void
    {
        static::assertSame('BEFOREOLDAFTER', $this->render());
    }

    private function render(): string
    {
        $twig = new Environment(new ArrayLoader([
            'base' => 'BEFORE{## @deprecated tag:v6.8.0 ##}{% block old %}OLD{% endblock %}AFTER',
        ]));
        $twig->addExtension(new FeatureFlagExtension());

        return $twig->render('base');
    }
}
