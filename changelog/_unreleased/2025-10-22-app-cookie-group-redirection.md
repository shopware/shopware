---
title: Allow apps to assign cookies to standard cookie groups via manifest
issue: NEXT-5725
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Added `default-target-group` attribute to `<cookies>` element in manifest XSD for app-level cookie group assignment
* Added `target-group` attribute to `<group>` element in manifest XSD for per-group cookie group override
* Changed `Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies` to parse new target group attributes from manifest
* Changed `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to propagate default target group to cookie metadata
* Changed `Shopware\Core\Framework\App\Cookie\AppCookieCollectListener` to redirect app cookies to standard groups based on manifest configuration
___
# Upgrade Information

## App Cookie Group Redirection

Apps can now assign their cookies to Shopware's standard cookie groups (`cookie.groupRequired`, `cookie.groupStatistical`, `cookie.groupComfortFeatures`, `cookie.groupMarketing`) instead of creating app-specific cookie groups.

### Before
Previously, each app created its own cookie groups, leading to many individual app-specific groups in the cookie consent manager:
```xml
<cookies>
    <group>
        <snippet-name>myapp.analytics</snippet-name>
        <entries>...</entries>
    </group>
</cookies>
```
Result: A cookie group named "myapp.analytics" is created.

### After
Apps can now redirect their cookies to standard groups:

#### Option 1: App-level default for all cookie groups
```xml
<cookies default-target-group="cookie.groupStatistical">
    <group>
        <snippet-name>myapp.analytics</snippet-name>
        <entries>...</entries>
    </group>
    <group>
        <snippet-name>myapp.tracking</snippet-name>
        <entries>...</entries>
    </group>
</cookies>
```
Result: Both cookie groups are assigned to the "Statistical" group.

#### Option 2: Per-group override
```xml
<cookies default-target-group="cookie.groupMarketing">
    <group target-group="cookie.groupStatistical">
        <snippet-name>myapp.analytics</snippet-name>
        <entries>...</entries>
    </group>
    <group>
        <snippet-name>myapp.tracking</snippet-name>
        <entries>...</entries>
    </group>
</cookies>
```
Result: 
- `myapp.analytics` → Statistical group (per-group override)
- `myapp.tracking` → Marketing group (app-level default)
