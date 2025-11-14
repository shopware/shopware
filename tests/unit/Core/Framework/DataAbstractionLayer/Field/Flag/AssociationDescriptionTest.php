<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AssociationDescription;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AssociationDescription::class)]
#[Package('framework')]
class AssociationDescriptionTest extends TestCase
{
    public function testConstructorSetsDescription(): void
    {
        $description = 'Main product image displayed in listings and detail pages';
        $flag = new AssociationDescription(description: $description);

        static::assertSame($description, $flag->getDescription());
    }

    public function testGetDescriptionReturnsCorrectValue(): void
    {
        $flag = new AssociationDescription('The category this entity belongs to');

        static::assertSame('The category this entity belongs to', $flag->getDescription());
    }

    public function testParseReturnsDescriptionInGenerator(): void
    {
        $description = 'Related products for cross-selling';
        $flag = new AssociationDescription($description);

        $result = iterator_to_array($flag->parse());

        static::assertArrayHasKey('association_description', $result);
        static::assertSame($description, $result['association_description']);
    }

    public function testHandlesEmptyDescription(): void
    {
        $flag = new AssociationDescription('');

        static::assertSame('', $flag->getDescription());

        $result = iterator_to_array($flag->parse());
        static::assertSame('', $result['association_description']);
    }

    public function testHandlesLongDescription(): void
    {
        $longDescription = 'This is a very detailed description that explains the purpose and usage of this association field in great detail, including when developers should use it and what data it contains.';
        $flag = new AssociationDescription($longDescription);

        static::assertSame($longDescription, $flag->getDescription());
    }

    public function testHandlesSpecialCharactersInDescription(): void
    {
        $description = 'Description with special characters: <>&"\'';
        $flag = new AssociationDescription($description);

        static::assertSame($description, $flag->getDescription());
    }

    public function testHandlesMultilineDescription(): void
    {
        $description = "First line\nSecond line\nThird line";
        $flag = new AssociationDescription($description);

        static::assertSame($description, $flag->getDescription());
    }
}
