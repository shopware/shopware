---
title: Allow apps to assign cookies to standard cookie groups
issue: NEXT-5725
---
# Core
* Added optional `default-target-group` attribute to `<cookies>` element in `manifest-3.0.xsd` to set a default target cookie group for all cookies in an app
* Added optional `target-group` attribute to `<group>` element in `manifest-3.0.xsd` to override the target cookie group for individual cookie groups
* Changed `Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies::parse` to extract the `default-target-group` attribute from the manifest
* Changed `Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies::parseChild` to extract the `target-group` attribute from cookie groups
* Changed `Shopware\Core\Framework\App\Lifecycle\AppLifecycle::getAppMetadata` to propagate the `default_target_group` from the `<cookies>` element to individual cookie entries
* Changed `Shopware\Core\Framework\App\Cookie\AppCookieCollectListener::addCookies` to support redirecting app cookie groups to Shopware's standard cookie groups based on manifest configuration
* Changed `Shopware\Core\Framework\App\Cookie\AppCookieCollectListener::addCookies` to use entries collection when cookies are redirected to support merging multiple apps into the same standard group
* Added `Shopware\Core\Framework\App\Cookie\AppCookieCollectListener::determineTargetGroup` to implement priority cascade for determining the target cookie group:
  1. `target-group` attribute on individual `<group>` element (highest priority)
  2. `default-target-group` attribute on `<cookies>` element
  3. Original `snippet_name` (backward compatible, lowest priority)
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

### Available Standard Groups
- `cookie.groupRequired` - Technically Required
- `cookie.groupStatistical` - Statistical  
- `cookie.groupComfortFeatures` - Comfort Features
- `cookie.groupMarketing` - Marketing

### Benefits
- **Consolidation**: Multiple apps can contribute to the same standard cookie group
- **Clarity**: Store operators see cookies organized by purpose, not by app
- **Compliance**: Easier to manage cookie consent categories for legal compliance
- **Backward Compatible**: Existing apps without these attributes continue to work unchanged

### Priority Cascade
The target cookie group is determined by this priority order:
1. `target-group` attribute on `<group>` element (highest priority)
2. `default-target-group` attribute on `<cookies>` element
3. Original `snippet_name` value (backward compatible, lowest priority)

