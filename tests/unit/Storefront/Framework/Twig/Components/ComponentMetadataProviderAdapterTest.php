<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Components;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Twig\Components\ComponentMetadataProviderAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentTemplateFinderInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(ComponentMetadataProviderAdapter::class)]
class ComponentMetadataProviderAdapterTest extends TestCase
{
    public function testMetadataForDelegatesToComponentFactory(): void
    {
        $componentConfig = [
            'key' => 'Sw:Alert',
            'template' => 'components/Sw/Alert/index.html.twig',
        ];

        // ComponentFactory is final and cannot be mocked. Seeding $config makes metadataFor()
        // return immediately without touching the other constructor dependencies.
        $factory = new ComponentFactory(
            $this->createMock(ComponentTemplateFinderInterface::class),
            new ServiceLocator([]),
            $this->createMock(PropertyAccessorInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            ['Sw:Alert' => $componentConfig],
            [],
            $this->createMock(Environment::class),
        );

        $adapter = new ComponentMetadataProviderAdapter($factory);

        $result = $adapter->metadataFor('Sw:Alert');

        static::assertSame('Sw:Alert', $result->getName());
        static::assertSame('components/Sw/Alert/index.html.twig', $result->getTemplate());
    }
}
