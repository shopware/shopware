---
title: Add possibility for plugins to add a HTML file
date: 2021-11-23
area: administration
tags: [plugin, admin, extension-api]
---

## Context
The new ExtensionAPI is based on a iFrame communication architecture. The old App system for the admin relies on the XML
file. And the normal plugin architecture in the admin is based on component overriding. The ideal way for developing
admin extensions will be the ExtensionAPI.

## Decision
To provide a smooth transition for plugin developer to the new ExtensionAPI which will be introduced soon we need to make sure that plugin can also
behave like Apps in the administration. To fulfill this we need to provide a solution to show their own iFrame views.
This is now directly possible when the plugin developer adds a `index.html` file to the plugin in the administration folder.

This file will automatically be used by webpack and can be used like a normal web application.
