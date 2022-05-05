---
title: Allow Apps to require additional privileges
issue: NEXT-20418
---
# Core
* Changed `manifest-1.0.xsd` file, to allow apps to require additional (non-CRUD) privileges.
* Changed parsing of app manifest files, to parse new field correctly.
* Added `additional_privileges` section to `\Shopware\Core\Framework\Store\Helper\PermissionCategorization`
___
# Administration
* Changed `sw-extension-permission-modal` to correctly parse the additional privileges.
___
# Upgrade Information
## Apps can now require additional ACL privileges

In addition to requiring CRUD-permission on entity basis, apps can now also require additional ACL privileges.
```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
    ...
    </meta>
    <permissions>
        <create>product</create>
        <update>product</update>
        <permission>user_change_me</permission>
    </permissions>
</manifest>
```
