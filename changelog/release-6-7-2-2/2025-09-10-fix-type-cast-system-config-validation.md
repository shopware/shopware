---
title: Fix type error when using named arguments in the Length validation constraint
author: Grzegorz Jan Rolka
author_email: grzegorz.rolka@pickware.de
author_github: @grzegorzrolka
---

# Core
* Changed Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator to fix unsupported argument type when creating `Length` constraint with named arguments.  XML configuration values such as `<maxLength>10</maxLength>` were parsed as strings and passed directly to `Symfony\Component\Validator\Constraints\Length::__construct()`, which now requires `int|null` for `min` and `max`.
