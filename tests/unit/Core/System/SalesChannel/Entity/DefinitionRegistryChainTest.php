<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(DefinitionRegistryChain::class)]
class DefinitionRegistryChainTest extends TestCase
{
    private DefinitionInstanceRegistry&MockObject $definitionInstanceRegistry;

    private SalesChannelDefinitionInstanceRegistry&MockObject $salesChannelDefinitionInstanceRegistry;

    private DefinitionRegistryChain $definitionRegistryChain;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->salesChannelDefinitionInstanceRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);
        $this->definitionRegistryChain = new DefinitionRegistryChain(
            $this->definitionInstanceRegistry,
            $this->salesChannelDefinitionInstanceRegistry
        );
    }

    public function testGetRepository(): void
    {
        $this->salesChannelDefinitionInstanceRegistry
            ->expects(static::once())
            ->method('getSalesChannelRepository')
            ->willThrowException(new SalesChannelRepositoryNotFoundException('product_manufacturer'));

        $this->definitionInstanceRegistry
            ->expects(static::once())
            ->method('getRepository')
            ->willReturn($this->createMock(EntityRepository::class));

        $repository = $this->definitionRegistryChain->getRepository('product_manufacturer');

        static::assertInstanceOf(EntityRepository::class, $repository);
    }

    public function testGetSalesChannelRepository(): void
    {
        $this->salesChannelDefinitionInstanceRegistry
            ->expects(static::once())
            ->method('getSalesChannelRepository')
            ->willReturn($this->createMock(SalesChannelRepository::class));

        $repository = $this->definitionRegistryChain->getRepository('category');

        static::assertInstanceOf(SalesChannelRepository::class, $repository);
    }
}
