---
title: Disable Vue compat mode per component level
date: 2024-03-11
area: administration
tags: [administration, vue, vue3, compat]
---

## Context

Vue 3 introduced a new compatibility mode to ease the migration from Vue 2 to Vue 3. This mode is enabled by default
and allows the use of most Vue 2 features in a Vue 3 application. This mode is only recommended for the transition period
and should be turned off as soon as possible.

We have kept the compatibility mode enabled in the administration because it makes it easier for plugins to migrate 
and results in fewer breaking changes during the major release. This splits the work of migrating the administration
and the plugins into two separate majors instead of one.

## Decision

Migrating all components in one by disabling the compatibility mode for the whole administration is a huge task and
would make it hard to keep the administration stable during the migration. We decided to disable the compatibility mode
per component level. This allows us to migrate components one by one and keep the administration stable during the migration.

This gives all teams and plugin developers the possibility to migrate their components to Vue 3 without waiting for the
whole administration to be migrated and for the global removal of the compatibility mode.

To activate the new mode, the `DISABLE_VUE_COMPAT` feature flag must be enabled. Then, it is possible to disable the
compatibility mode on a per-component level by setting the `compatConfig` option in the component to our custom configuration.
This custom configuration is exposed in `Shopware.compatConfig` and has all compatibility features disabled if the 
feature flag is activated.

### Example

```javascript
Shopware.Component.register('your-component', {
    compatConfig: Shopware.compatConfig,
})
```

#### Notice:
We have a tool which reads all components and creates a list of all components which are still using the
compatibility mode. This list is used to track the progress of the migration. This tool checks for the following
syntax `compatConfig: Shopware.compatConfig,` inside the component definition. Any other syntax, e.g. `compatConfig: false,`
will not be recognized by the tool and will not be tracked.

## Consequences

Migration to Vue 3 can be done incrementally, and administration remains stable during migration. Also, it allows us to
migrate the admin and plugins separately, making the migration easier for all teams and plugin developers.

To accomplish this task we have to communicate the new feature flag and the new way to disable the compatibility mode
to all teams and plugin developers. This will give them the opportunity to migrate their components to Vue 3 over a 
longer period of time period of time.
