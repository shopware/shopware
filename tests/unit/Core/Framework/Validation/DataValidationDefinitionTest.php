<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @internal
 */
#[CoversClass(DataValidationDefinition::class)]
class DataValidationDefinitionTest extends TestCase
{
    public function testMergeSubDefinitionsWhenBothExist(): void
    {
        // Create base definition with a sub definition
        $baseDefinition = new DataValidationDefinition('base');
        $baseSubDefinition = new DataValidationDefinition('sub');
        $baseSubDefinition->add('field1', new NotBlank());
        $baseDefinition->addSub('sub', $baseSubDefinition);

        // Create definition to merge with additional constraints in sub definition
        $mergeDefinition = new DataValidationDefinition('merge');
        $mergeSubDefinition = new DataValidationDefinition('sub');
        $mergeSubDefinition->add('field2', new NotNull());
        $mergeDefinition->addSub('sub', $mergeSubDefinition);

        // Merge definitions
        $result = $baseDefinition->merge($mergeDefinition);

        // Get the merged sub definition
        $mergedSubDefinition = $result->getSubDefinitions()['sub'];

        // Verify that both constraints exist in the merged sub definition
        static::assertCount(1, $mergedSubDefinition->getProperty('field1'));
        static::assertInstanceOf(NotBlank::class, $mergedSubDefinition->getProperty('field1')[0]);
        static::assertCount(1, $mergedSubDefinition->getProperty('field2'));
        static::assertInstanceOf(NotNull::class, $mergedSubDefinition->getProperty('field2')[0]);
    }

    public function testMergeSubDefinitionsWhenOnlyBaseExists(): void
    {
        // Create base definition with a sub definition
        $baseDefinition = new DataValidationDefinition('base');
        $baseSubDefinition = new DataValidationDefinition('sub');
        $baseSubDefinition->add('field1', new NotBlank());
        $baseDefinition->addSub('sub', $baseSubDefinition);

        // Create definition to merge with a new sub definition
        $mergeDefinition = new DataValidationDefinition('merge');
        $mergeSubDefinition = new DataValidationDefinition('newSub');
        $mergeSubDefinition->add('field2', new NotNull());
        $mergeDefinition->addSub('newSub', $mergeSubDefinition);

        // Merge definitions
        $result = $baseDefinition->merge($mergeDefinition);

        // Verify that both sub definitions exist
        static::assertArrayHasKey('sub', $result->getSubDefinitions());
        static::assertArrayHasKey('newSub', $result->getSubDefinitions());

        // Verify the original sub definition is unchanged
        $originalSub = $result->getSubDefinitions()['sub'];
        static::assertCount(1, $originalSub->getProperty('field1'));
        static::assertInstanceOf(NotBlank::class, $originalSub->getProperty('field1')[0]);

        // Verify the new sub definition was added correctly
        $newSub = $result->getSubDefinitions()['newSub'];
        static::assertCount(1, $newSub->getProperty('field2'));
        static::assertInstanceOf(NotNull::class, $newSub->getProperty('field2')[0]);
    }

    public function testMergeListDefinitionsWhenBothExist(): void
    {
        // Create base definition with a list definition
        $baseDefinition = new DataValidationDefinition('base');
        $baseListDefinition = new DataValidationDefinition('list');
        $baseListDefinition->add('field1', new NotBlank());
        $baseDefinition->addList('list', $baseListDefinition);

        // Create definition to merge with additional constraints in list definition
        $mergeDefinition = new DataValidationDefinition('merge');
        $mergeListDefinition = new DataValidationDefinition('list');
        $mergeListDefinition->add('field2', new NotNull());
        $mergeDefinition->addList('list', $mergeListDefinition);

        // Merge definitions
        $result = $baseDefinition->merge($mergeDefinition);

        // Get the merged list definition
        $mergedListDefinition = $result->getListDefinitions()['list'];

        // Verify that both constraints exist in the merged list definition
        static::assertCount(1, $mergedListDefinition->getProperty('field1'));
        static::assertInstanceOf(NotBlank::class, $mergedListDefinition->getProperty('field1')[0]);
        static::assertCount(1, $mergedListDefinition->getProperty('field2'));
        static::assertInstanceOf(NotNull::class, $mergedListDefinition->getProperty('field2')[0]);
    }

    public function testMergeListDefinitionsWhenOnlyBaseExists(): void
    {
        // Create base definition with a list definition
        $baseDefinition = new DataValidationDefinition('base');
        $baseListDefinition = new DataValidationDefinition('list');
        $baseListDefinition->add('field1', new NotBlank());
        $baseDefinition->addList('list', $baseListDefinition);

        // Create definition to merge with a new list definition
        $mergeDefinition = new DataValidationDefinition('merge');
        $mergeListDefinition = new DataValidationDefinition('newList');
        $mergeListDefinition->add('field2', new NotNull());
        $mergeDefinition->addList('newList', $mergeListDefinition);

        // Merge definitions
        $result = $baseDefinition->merge($mergeDefinition);

        // Verify that both list definitions exist
        static::assertArrayHasKey('list', $result->getListDefinitions());
        static::assertArrayHasKey('newList', $result->getListDefinitions());

        // Verify the original list definition is unchanged
        $originalList = $result->getListDefinitions()['list'];
        static::assertCount(1, $originalList->getProperty('field1'));
        static::assertInstanceOf(NotBlank::class, $originalList->getProperty('field1')[0]);

        // Verify the new list definition was added correctly
        $newList = $result->getListDefinitions()['newList'];
        static::assertCount(1, $newList->getProperty('field2'));
        static::assertInstanceOf(NotNull::class, $newList->getProperty('field2')[0]);
    }

    public function testMergeProperties(): void
    {
        // Create base definition with properties
        $baseDefinition = new DataValidationDefinition('base');
        $baseDefinition->add('field1', new NotBlank());

        // Create definition to merge with additional properties
        $mergeDefinition = new DataValidationDefinition('merge');
        $mergeDefinition->add('field1', new NotNull()); // Add to existing field
        $mergeDefinition->add('field2', new NotBlank()); // Add new field

        // Merge definitions
        $result = $baseDefinition->merge($mergeDefinition);

        // Verify that both constraints exist for field1
        static::assertCount(2, $result->getProperty('field1'));
        static::assertInstanceOf(NotBlank::class, $result->getProperty('field1')[0]);
        static::assertInstanceOf(NotNull::class, $result->getProperty('field1')[1]);

        // Verify that field2 was added correctly
        static::assertCount(1, $result->getProperty('field2'));
        static::assertInstanceOf(NotBlank::class, $result->getProperty('field2')[0]);
    }
}
