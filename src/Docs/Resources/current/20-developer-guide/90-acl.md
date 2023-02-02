[titleEn]: <>(ACL)
[hash]: <>(article:developer_acl)

## ACL

The Access Control List or ACL in Shopware ensures that you can create individual roles. 
These roles have finely granular rights, which every shop operator can set up himself. 
These roles can be assigned to users.

For an overview on ACL please take a look at [ACL in the administration](./../20-developer-guide/100-administration/70-acl.md)

## Protect your custom routes with ACL

If you create a route which needs specific protection outside of the entity permissions,
you can add your own ACL privilege by adding the annotation `@Acl({"my_plugin_do_something"})`.


```php
/**
     * @Route("/api/_action/do-something", name="my.plugin.api.action.do.something", methods={"POST"})
     * @Acl({"my_plugin_do_something"})
     */
    public function doSomething(): Response
    {
        //Can only be called by users with admin-privilege or the privilege 'my_plugin_do_something'
    }
```

If your route only makes use of entities and don't need special protection, you don't need to do anything special with the route, just add the privilege to the role like mentions below. 

## Add your custom privileges

To make sure your custom privileges are additionally written to existing roles,
you have to add them by override the `enrichPrivileges` method in the Plugin Class. 
This is only to ensure that the new privileges are available directly after the activation of the plugin, 
you still have to add the privileges to the administration as described in [the administration article](./../20-developer-guide/100-administration/70-acl.md).

```php
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;

class SwagTestPluginAcl extends Plugin
{
    //my_plugin_do_something = a custom route privilege
    //my_plugin_entity:read/create/update/delete = the privileges for your custom entity
    public function enrichPrivileges(): array
    {
        return [
            'product.viewer' => [
                'my_plugin_do_something',
                'my_plugin_entity:read',
                'my_plugin_entity:update',
                'my_plugin_entity:delete',
                'my_plugin_entity:create'
            ],
            'product.editor' => [
                'my_plugin_do_something',
                'my_plugin_entity:read',
                'my_plugin_entity:update',
                'my_plugin_entity:delete',
                'my_plugin_entity:create'
            ],
            AclRoleDefinition::ALL_ROLE_KEY => [
                'open_for_everyone:read',
                'open_for_everyone:update',
                'open_for_everyone:delete',
                'open_for_everyone:create'
            ]
        ];
    }
}
```

The `enrichPrivileges` method needs to return the roles for which the privileges should be added and an array of privileges for the roles.
The key ```AclRoleDefinition::ALL_ROLE_KEY``` ('all') is automatically assigned to all users with at least one role. This key should only be used for entities that should be readable for everyone.
