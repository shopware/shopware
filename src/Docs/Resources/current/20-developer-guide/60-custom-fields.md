[titleEn]: <>(Custom fields)
[hash]: <>(article:developer_custom_fields)

## Custom fields guide

Shopware's custom field system allows you to extend existing entities, without
writing a complete entity extension. This is possible by storing the additional
data in a
[JSON-Field](https://dev.mysql.com/doc/refman/8.0/en/json.html)
. Custom fields therefore can only be used to store scalar values. If you'd like
to create associations between entities, you'll need to use an
[entity extension](./../50-how-to/180-entity-extension.md)
.

### Adding custom fields

To add custom fields to an entity, you can use the custom fieldset repository,
which can be retrieved from the dependency injection container via the
`custom_field_set.repository` key. The class returned implements the
[EntityRepositoryInterface](https://github.com/shopware/platform/blob/master/src/Core/Framework/DataAbstractionLayer/EntityRepositoryInterface.php)
and can be used like any other repository. You may annotate it accordingly for
convenience:

```php
// SwagExamplePlugin/src/Service/SwagCustomFieldSetService.php

<?php declare(strict_types=1);

class SwagCustomFieldSetService {

    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldSetRepository;

    public function __construct(EntityRepositoryInterface $customFieldSetRepository)
    {
        $this->customFieldSetRepository = $customFieldSetRepository;

        // ...
    }

    // ...

}
```

Now that that's done, you can use the repository to add new custom fields:

```php
$this->customFieldSetRepository->create([
    [
        'name' => 'swag_example',
        'customFields' => [
            ['name' => 'swag_example_size', 'type' => CustomFieldTypes::INT],
            ['name' => 'swag_example_color', 'type' => CustomFieldTypes::TEXT]
        ]
    ]
], $context);
```

As you may have noticed, the repository used here is the
`custom_field_`**`set`**`_repository` and the data structure encapsulates
multiple `customFields`. This is the case, because these are user-editable
custom fields which will be visible in the custom field settings in the
administration. If you don't want users of your plugin to change any of the
custom field settings, you can also omit the relation to a fieldset, by adding
the fields to an entity directly:

```php
$this->productRepository->upsert([[
    'id' => '0a1b1ab305a94debb53d5aedf5349b8c',
    'customFields' => ['swag_example_size' => 15, 'swag_example_color' => '#189eff']
]], $context);
```

You can now use these custom fields in criteria:

```php
$this->productRepository->search(
    (new Criteria())->addFilter(new EqualsFilter('customFields.swag_example_color', '#189eff'))
, $context);
```

### Global custom field sets
It is possible to make custom field sets "global". That means that the administration user
 is not able to change its custom fields. Also, a global set cannot be hidden on the product detail page.
This is meant for plugins which rely on certain custom fields to exist and prevents the user from deleting
them in the administration. 
Here is a small example:
```php
$this->customFieldSetRepository->create([
    [
        'name' => 'swag_example_set',
        'global' => true,
        'config' => [
            'label' => [
                'de-DE' => 'Beispiel Plugin Zusatzfeld Set',
                'en-GB' => 'Example plugin custom field set'
            ]
        ],
        'relations' => [[
            'entityName' => 'product'
        ]],
        'customFields' => [
            ['name' => 'swag_example_size', 'type' => CustomFieldTypes::INT],
            ['name' => 'swag_example_color', 'type' => CustomFieldTypes::TEXT]
        ]
    ]
], $context);
```
