[titleEn]: <>(Write Protection)

The Shopware Platform has a few read optimized fields that should usually not be written by users of the DAL or even the REST-API. But of course there always is a single place where manipulation must be possible. Learn here how.

### Flagging the field

You need to flag the field in your `EntityDefinition` with `WriteProtected` and add a unique identifier to reference the protection.

```php
(new StringField('protected', 'protected'))->addFlags(new WriteProtected('permission_key_example'));
```

### Handler

In your own handler with your custom logic you can now easily add the required permission key to the context. And excute your write operation.

```
$context->getWriteProtection()->allow('permission_key_example');
```

If the defined permissionKey is not set in the contexts' write protection, the DataAbstractionLayer will throw a `InsufficientWritePermissionException`.