<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Api\SpecificationSourceLocator;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(SpecificationSourceLocator::class)]
class SpecificationSourceLocatorTest extends TestCase
{
    #[TestDox('selects the entity source whose supportsEntityType matches')]
    public function testResolvesByEntityType(): void
    {
        $product = $this->sourceSupporting('product');
        $category = $this->sourceSupporting('category');

        $locator = new SpecificationSourceLocator([$category, $product], new ServiceLocator([]));

        static::assertSame($product, $locator->resolveByEntityType('product'));
    }

    #[TestDox('selects the section source from the locator')]
    public function testResolvesBySection(): void
    {
        $header = static::createStub(AbstractSpecificationSource::class);

        $locator = new SpecificationSourceLocator([], new ServiceLocator(['header' => static fn (): AbstractSpecificationSource => $header]));

        static::assertSame($header, $locator->resolveBySection(ContentSection::HEADER));
    }

    #[TestDox('throws unknownEntityType when no entity source supports the type')]
    public function testThrowsForUnknownEntityType(): void
    {
        $locator = new SpecificationSourceLocator([$this->sourceSupporting('product')], new ServiceLocator([]));

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('mystery'));

        $locator->resolveByEntityType('mystery');
    }

    #[TestDox('throws noSourceForSection when the section has no registered source')]
    public function testThrowsForUnregisteredSection(): void
    {
        $locator = new SpecificationSourceLocator([], new ServiceLocator([]));

        $this->expectExceptionObject(ContentSystemException::noSourceForSection('footer'));

        $locator->resolveBySection(ContentSection::FOOTER);
    }

    private function sourceSupporting(string $entityType): AbstractSpecificationSource
    {
        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('supportsEntityType')->willReturnCallback(static fn (string $type): bool => $type === $entityType);

        return $source;
    }
}
