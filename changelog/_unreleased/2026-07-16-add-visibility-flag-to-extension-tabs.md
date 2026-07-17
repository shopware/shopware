---
title: Add visibility flag to extension tabs
author: Mark Ng
---
# Administration
* Added optional `visible` property to `sw.ui.tabs().addTabItem()` so an app can show or hide its own registered tab (for example based on the currently opened entity)
* Changed the `tabs` store `addTabItem` action to upsert by `componentSectionId`, so re-registering the same tab updates it instead of appending a duplicate
* Changed `mt-tabs` and `sw-tabs-deprecated` to hide extension tab items whose `visible` is explicitly `false`
___
# Upgrade Information
## Conditional visibility for app-registered tabs
`sw.ui.tabs('<position>').addTabItem()` now accepts an optional `visible` boolean. When it is omitted the tab is shown, so existing extensions are unaffected. Pass `visible: false` to hide the tab, and re-send `addTabItem` for the same `componentSectionId` to toggle it for the current context — the tab store now upserts by `componentSectionId` instead of appending a new entry.
