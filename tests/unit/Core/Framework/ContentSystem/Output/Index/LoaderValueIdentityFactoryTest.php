<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfigSerializer;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoaderValueIdentityFactory::class)]
class LoaderValueIdentityFactoryTest extends TestCase
{
    #[TestDox('hashes an object input by instance without serializing its object graph')]
    public function testObjectInputUsesInstanceIdentity(): void
    {
        $input = new \stdClass();
        $input->recursive = $input;
        $factory = $this->factory();
        $requirement = new DataRequirement('result', 'entity', new StubLoaderConfig());

        $first = $factory->create($requirement, new LoaderInputs(['input' => $input]), null);
        $second = $factory->create($requirement, new LoaderInputs(['input' => $input]), null);

        static::assertSame($first->inputsHash, $second->inputsHash);
    }

    #[TestDox('distinguishes object input instances even when their contents are equal')]
    public function testDistinctObjectInputsHaveDifferentIdentities(): void
    {
        $firstInput = (object) ['value' => 'same'];
        $secondInput = (object) ['value' => 'same'];
        $factory = $this->factory();
        $requirement = new DataRequirement('result', 'entity', new StubLoaderConfig());

        $first = $factory->create($requirement, new LoaderInputs(['input' => $firstInput]), null);
        $second = $factory->create($requirement, new LoaderInputs(['input' => $secondInput]), null);

        static::assertNotSame($first->inputsHash, $second->inputsHash);
    }

    private function factory(): LoaderValueIdentityFactory
    {
        return new LoaderValueIdentityFactory(
            new DataLoaderConfigSerializerProvider(new ServiceLocator([
                'entity' => static fn (): StubLoaderConfigSerializer => new StubLoaderConfigSerializer(),
            ])),
            new ConfigCanonicalizer(),
            new ValueFingerprinter(),
        );
    }
}
