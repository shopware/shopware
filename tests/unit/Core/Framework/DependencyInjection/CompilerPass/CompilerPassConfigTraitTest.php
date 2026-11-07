<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass\Stub\ExampleCompilerPass;
use Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass\Stub\MockFrameworkExtension;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversTrait(CompilerPassConfigTrait::class)]
class CompilerPassConfigTraitTest extends TestCase
{
    public function testAutoConfigure(): void
    {
        $parameterBag = new EnvPlaceholderParameterBag();
        $container = new ContainerBuilder($parameterBag);
        $container->setParameter('kernel.debug', true);

        $container->registerExtension(new MockFrameworkExtension());
        $container->prependExtensionConfig('framework', [
            'http_cache' => [
                'default_ttl' => '%env(int:DUMMY_ENV)%',
            ],
        ]);

        $container->addCompilerPass(new ValidateEnvPlaceholdersPass());
        $container->addCompilerPass(new ExampleCompilerPass());

        $this->expectNotToPerformAssertions();
        $container->compile(true);
    }
}
