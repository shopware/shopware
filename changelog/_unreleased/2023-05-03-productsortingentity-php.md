---
title: ProductSortingEntity.php
issue: NEXT-23654
author: AnimeGuru
author_email: melanityt@gmail.com
author_github: AnimeGuru
---
# Core
* Changed `createDalSorting()` in `Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity` to include a Fallback Sorting Field for each Sorting preventing undeterministic behaviour with ORDER BY with entries of the same value when using OFFSET (e.g. Pagination).
___
# Upgrade Information
## Before
```php
public function createDalSorting(): array
{
    $sorting = [];

    $fields = $this->fields;

    usort($fields, fn ($a, $b) => $b['priority'] <=> $a['priority']);

    foreach ($fields as $field) {
        $direction = mb_strtoupper((string) $field['order']) === FieldSorting::ASCENDING
            ? FieldSorting::ASCENDING
            : FieldSorting::DESCENDING;

        $sorting[] = new FieldSorting(
            $field['field'],
            $direction,
            (bool) ($field['naturalSorting'] ?? false)
        );
    }

    return $sorting;
}
```
## After
```php
public function createDalSorting(): array
{
    $sorting = [];

    $fields = $this->fields;
    $fields[] = , $this->getFallbackSortingField();

    usort($fields, fn ($a, $b) => $b['priority'] <=> $a['priority']);

    foreach ($fields as $field) {
        $direction = mb_strtoupper((string) $field['order']) === FieldSorting::ASCENDING
            ? FieldSorting::ASCENDING
            : FieldSorting::DESCENDING;

        $sorting[] = new FieldSorting(
            $field['field'],
            $direction,
            (bool) ($field['naturalSorting'] ?? false)
        );
    }

    return $sorting;
}

/** This ensures deterministic behaviour with duplicate keys in ORDER BY and OFFSET between queries */
private function getFallbackSortingField(): array {
    return [
        'field' => 'product.id',
        'order' => FieldSorting::ASCENDING,
        'priority' => -1,
        'naturalSorting' => false,
    ];
}
```
