<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Api\SpecificationSourceResolver;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(SpecificationSourceResolver::class)]
class SpecificationSourceResolverTest extends TestCase
{
    #[TestDox('selects the entity source whose supportsEntityType matches')]
    public function testResolvesByEntityType(): void
    {
        $product = $this->sourceSupporting('product');
        $category = $this->sourceSupporting('category');

        $resolver = new SpecificationSourceResolver([$category, $product], new ServiceLocator([]));

        static::assertSame($product, $resolver->resolveByEntityType('product'));
    }

    #[TestDox('throws unknownEntityType when no entity source supports the type')]
    public function testThrowsForUnknownEntityType(): void
    {
        $resolver = new SpecificationSourceResolver([$this->sourceSupporting('product')], new ServiceLocator([]));

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('mystery'));

        $resolver->resolveByEntityType('mystery');
    }

    #[TestDox('selects the section source from the locator')]
    public function testResolvesBySection(): void
    {
        $header = $this->createMock(AbstractSpecificationSource::class);

        $resolver = new SpecificationSourceResolver([], new ServiceLocator(['header' => static fn (): AbstractSpecificationSource => $header]));

        static::assertSame($header, $resolver->resolveBySection(ContentSection::HEADER));
    }

    #[TestDox('throws noSourceForSection when the section has no registered source')]
    public function testThrowsForUnregisteredSection(): void
    {
        $resolver = new SpecificationSourceResolver([], new ServiceLocator([]));

        $this->expectExceptionObject(ContentSystemException::noSourceForSection('footer'));

        $resolver->resolveBySection(ContentSection::FOOTER);
    }

    private function sourceSupporting(string $entityType): AbstractSpecificationSource
    {
        $source = $this->createMock(AbstractSpecificationSource::class);
        $source->method('supportsEntityType')->willReturnCallback(static fn (string $type): bool => $type === $entityType);

        return $source;
    }
}
