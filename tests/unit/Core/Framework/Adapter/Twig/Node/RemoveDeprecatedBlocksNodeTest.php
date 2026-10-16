<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\RemoveDeprecatedBlocksNode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RemoveDeprecatedBlocksNode::class)]
class RemoveDeprecatedBlocksNodeTest extends TestCase
{
    use EnvTestBehaviour;

    protected function setUp(): void
    {
        Feature::registerFeatures(['v6.8.0.0', 'TWIG_COMPILE_TIME_OPTIMIZATION']);
    }

    public function testCachedTemplateClassRegistersBlocksForEachNewEnvironment(): void
    {
        $loader = new ArrayLoader(['base' => '{## @deprecated tag:v6.8.0 ##}{% block old %}OLD{% endblock %}']);

        $this->setEnvVars(['V6_8_0_0' => '0', 'TWIG_COMPILE_TIME_OPTIMIZATION' => '0']);
        $legacy = new Environment($loader);
        $legacy->addExtension(new FeatureFlagExtension());
        static::assertTrue($legacy->load('base')->hasBlock('old'));
        static::assertSame('OLD', $legacy->render('base'));

        $this->setEnvVars(['V6_8_0_0' => '1']);
        $major = new Environment($loader);
        $major->addExtension(new FeatureFlagExtension());
        static::assertTrue(Feature::isActive('v6.8.0.0'));
        static::assertSame($legacy->getTemplateClass('base'), $major->getTemplateClass('base'));
        static::assertFalse($major->load('base')->hasBlock('old'));
        static::assertSame('', $major->render('base'));

        $this->setEnvVars(['V6_8_0_0' => '0']);
        $restored = new Environment($loader);
        $restored->addExtension(new FeatureFlagExtension());
        static::assertTrue($restored->load('base')->hasBlock('old'));
        static::assertSame('OLD', $restored->render('base'));
    }
}
