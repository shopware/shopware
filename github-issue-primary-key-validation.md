# Add validation for primary key consistency between database schema and entity definition

## Summary

The DAL currently fails silently when entity definitions have `PrimaryKey` flags that don't match the actual database PRIMARY KEY constraint. This results in confusing behavior where API queries return `total` counts but empty `data` arrays, making debugging extremely difficult.

## Current Behavior

When a custom entity definition has mismatched primary key definitions:

**Database Schema:**
```sql
CREATE TABLE `custom_entity` (
    `id` BINARY(16) NOT NULL,
    `foreign_id` BINARY(16) NOT NULL,
    PRIMARY KEY (`id`)  -- Only 'id' is primary key
);
```

**Entity Definition:**
```php
class CustomEntityFields
{
    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('foreign_id', 'foreignId', OtherDefinition::class))
                ->addFlags(new PrimaryKey(), new Required(), new ApiAware()),  // ❌ Wrong!
        ]);
    }
}
```

**Result:**
- Admin API `/api/search/custom-entity` returns:
  ```json
  {
    "total": 5001,
    "data": []
  }
  ```
- No error is thrown
- Entity hydration fails silently
- Developers have no indication of what's wrong

## Expected Behavior

The `dal:validate` command should detect this mismatch and provide a clear error message:

```
[ERROR] Vendor\Plugin\CustomEntityDefinition
  ✗ Primary key mismatch in entity "custom_entity": Database has PRIMARY KEY (id),
    but entity definition has PrimaryKey flags on (id, foreign_id).
    This causes entity hydration to fail silently.
    Ensure PrimaryKey flags match the database schema exactly.
```

## Why This Happens

The entity hydrator expects the primary key structure from the definition to match the database. When they don't match:

1. Query executes successfully → `total` is correct ✅
2. Rows are fetched from database ✅
3. Entity hydration fails due to PK mismatch ❌
4. DAL silently skips failed entities to prevent API errors ❌
5. Result: empty `data` array with correct `total` count

This silent failure is intentional (to prevent API crashes), but without proper validation during development, it's nearly impossible to debug.

## Reproduction Steps

1. Create a custom entity with a single `id` PRIMARY KEY in the database
2. Add a `PrimaryKey` flag to a foreign key field in the entity definition
3. Run queries via EntityRepository or Admin API
4. Observe: `total` count is correct but `data` is empty
5. Run `bin/console dal:validate` - no error is shown

## Proposed Solution

Add a new validation method in `src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php`:

```php
/**
 * Validates that PrimaryKey flags in entity definition match the database PRIMARY KEY constraint
 *
 * @return array<class-string<EntityDefinition>, list<string>>
 */
private function validatePrimaryKeyConsistency(EntityDefinition $definition): array
{
    $violations = [];

    // Skip if table doesn't exist yet
    try {
        $table = $this->connection->createSchemaManager()->introspectTable($definition->getEntityName());
    } catch (\Exception) {
        return [];
    }

    // Get primary key columns from database
    $databasePrimaryKeys = $table->getPrimaryKey()?->getColumns() ?? [];

    // Get primary key fields from entity definition
    $definitionPrimaryKeys = $definition->getPrimaryKeys();
    $definitionPkColumns = [];

    foreach ($definitionPrimaryKeys as $pkField) {
        if (!$pkField instanceof StorageAware) {
            continue;
        }
        $definitionPkColumns[] = $pkField->getStorageName();
    }

    // Sort both arrays for consistent comparison
    sort($databasePrimaryKeys);
    sort($definitionPkColumns);

    // Check if they match
    if ($databasePrimaryKeys !== $definitionPkColumns) {
        $violations[] = sprintf(
            'Primary key mismatch in entity "%s": Database has PRIMARY KEY (%s), but entity definition has PrimaryKey flags on (%s). This causes entity hydration to fail silently. Ensure PrimaryKey flags match the database schema exactly.',
            $definition->getEntityName(),
            implode(', ', $databasePrimaryKeys),
            implode(', ', $definitionPkColumns)
        );
    }

    return [$definition->getClass() => $violations];
}
```

**Integration in `validate()` method (around line 184):**

```php
$violations = array_merge_recursive($violations, $this->validateSchema($definition));

// Add primary key consistency check
$violations = array_merge_recursive($violations, $this->validatePrimaryKeyConsistency($definition));

$violations = array_merge_recursive($violations, $this->validateColumn($definition));
```

## Benefits

1. **Early Detection**: Catches the issue during development/CI via `dal:validate`
2. **Clear Error Message**: Developers immediately know what's wrong and how to fix it
3. **Prevents Silent Failures**: Stops confusing debugging sessions where data exists but isn't returned
4. **Consistent with Existing Validation**: Follows the same pattern as other DAL validations

## Alternative Solutions Considered

1. **Runtime Exception During Hydration**: Would be more intrusive and could break existing (broken) installations
2. **Warning in Logs**: Easy to miss, doesn't prevent the issue
3. **Stricter Type Checking**: Would require major refactoring

The proposed solution is non-breaking and follows established patterns in `DefinitionValidator`.

## Impact

- **Breaking Changes**: None - this only adds validation
- **Performance**: Negligible - only runs during `dal:validate` command
- **Backwards Compatibility**: Fully compatible - will expose existing bugs but won't break functionality

## Additional Context

This issue can occur in several scenarios:
- Plugin developers unfamiliar with Shopware's DAL patterns
- Copy-paste errors when creating entity definitions
- Database migrations that change PRIMARY KEY structure without updating definitions
- Composite keys where only some fields should have the `PrimaryKey` flag

The issue can persist unnoticed for months if:
- The code worked initially (stricter validation in recent Shopware versions)
- Tests only check for `total` count, not actual data
- Manual testing focuses on write operations rather than reads

## Related

- Similar validation exists for: associations, field types, nullable columns, translation definitions
- This would complete the validation coverage for core entity definition components

