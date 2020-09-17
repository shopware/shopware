---
title:  Add support for autowiring of entity repositories
issue:  NEXT-10918
author:             Hendrik Söbbing
author_email:       hendrik@soebbing.de
author_github:      @soebbing
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to also register an alias for arguments
___
# Upgrade Information

## Entity Repository Autowiring

The DAL entity repositories can now be injected into your services using autowiring. Necessary for this to work
(apart from having your service configured for [autowiring](https://symfony.com/doc/current/service_container/autowiring.html) generally)
are:
- The type of the parameter. It needs to be `EntityRepositoryInterface`
- The name of the variable. It must be the same as the id of the service in the DIC, written in `camelCase` instead of `snake_case`, followed by the word `Repository`.

So for example, a media_thumbnail repository (id `media_thumbnail.repository`) would be requested (and injected) like this:
```php
public function __construct(EntityRepositoryInterface $mediaThumbnailRepository) {}
```
