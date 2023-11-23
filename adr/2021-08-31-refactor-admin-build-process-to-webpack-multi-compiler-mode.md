---
title: Refactor admin build process to webpack-multi-compiler mode
date: 2021-08-31
area: administration
tags: [administration, webpack, plugin, build]
---

## Context
Previously the plugins are not completely independent from the core and other plugins. This has sometimes caused built plugin files to be incompatible with the core. Unless they were rebuilt again with the core.

The reason for this was that dependencies between plugins and the Core were optimized by Webpack. This was because Webpack saw the combination of Core and plugins as one big program. So using tree-shaking, sometimes dependencies were removed or added depending on which plugins were installed.

Also, a custom Webpack configuration in plugins resulted in it unavoidably being applied in core as well. This could sometimes result in the plugin only being compatible with the core if both were built together. If the plugin was then installed on other systems with only the built files, it could cause it not to work.

## Decision
Webpack is known by many users and already in use. A switch to another builder needs to be deeply analyzed at first and then all plugin devs need to learn this bundler too, which can be frustrating, when you want to write a great plugin but has to learn a new bundler for no reason.

So the isolated compiling and production bundling will be realized with webpack. Webpack also provides a good way how to solve the problem. With the webpack-multi-compiler we can build several independent configurations which do not affect each other. The watch mode also works with this setup so that no developer needs to relearn something.

## Consequences
These potential errors are eliminated with the new mode. Each plugin is built completely isolated and cannot modify or affect other plugins or the core. A big advantage is that now plugin developers can customize the Webpack configuration as they wish, without having to worry about being incompatible with the core.

The complete refactoring is implemented in a backward compatible way. Therefore, no plugin developer has to change anything and can continue to develop as before. Only with the advantage that it is now more stable and secure. And with the flexibility to customize its own configuration as they like.
