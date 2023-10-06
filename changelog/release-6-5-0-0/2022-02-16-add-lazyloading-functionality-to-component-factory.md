---
title: Add lazy loading functionality to component factory
issue: NEXT-20067
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: @jleifeld
---
# Administration
* Changed Vue-Adapter behavior to create lazy Vue components
* Added `async-component-factory` with lazy component support
* Deprecated old `component.factory`
* Changed `template.factory` to support async resolution of templates
* Changed global `Shopware.Component` values to the new, `async-component.factory`
* Changed feature flag loading behavior so that it can directly be used in the boot process
