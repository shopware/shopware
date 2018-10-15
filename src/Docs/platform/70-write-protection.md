# WriteProtected flag

In some cases you want to restrict the write access to individual fields, so that they can't be manipulated through the entities CRUD-API.
For example if you have to run some custom logic before you can update a fields value. This can be accomplished with the WriteProtected Flag. 
If you set this Flag you have to define a permission key, that has to be set in the write-protection extension of the write operations context.

```
(new StringField('protected', 'protected'))->setFlags(new WriteProtected('permission_key_example'));
```

In your own controller with your custom logic you can easily add the required permission key to the context.

```
$context->getWriteProtection()->allow('permission_key_example');
```

If the defined permissionKey is not set in the context write protection, the ORM will throw a `InsufficientWritePermissionException`.