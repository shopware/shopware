# 2020-08-28 - Import ACL privileges from other roles

## Context
Some modules have components which require many acl privileges. Examples
are the rule builder or the media manager. Therefore, you need all privileges
in each module which have these components. Also you do not want to add the
module to the dependency section because then the user has full access to module
in the administration.

## Decision
To avoid duplication of these privileges we use a helper function. These
function returns all privileges from the other module dynamically. You can
use it directly in the privileges:

```js
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'promotion',
        roles: {
            viewer: {
                privileges: ['promotion:read',],
                dependencies: []
            },
            editor: {
                privileges: [
                    'promotion:update',
                    Shopware.Service('privileges').getPrivileges('rule.creator')
                ],
                dependencies: [
                    'promotion.viewer'
                ]
            }   
        }
    });
```

## Consequences
Each module contains only the relevant privileges for his module. All needed
privileges which are not directly mapped to the module can be imported. This
has the big benefit if someone changes something in the imported module all
other modules will be affected too.
