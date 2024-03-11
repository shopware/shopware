---
title: Providing the admin extension SDK
date: 2022-06-15
area: administration
tags: [meteor-extension-sdk, vue]
---

::: warning
The Admin Extension SDK has been renamed to Meteor Extension SDK.
:::

## Context
The Admin Extension SDK is a toolkit for plugin and app developers to extend or modify the administration via their plugins or apps. The SDK contains easy to use methods which interact with the administration in the background via the PostMessage API for iFrames.

## Decision
We created the SDK in a separate repository on GitHub instead of creating it in the platform repository. This decision has several reasons.

### Faster development
The main reason for the separate repository is the development speed. We can develop much faster. The whole, large pipeline does not need to run for small changes. Also the pipeline for the SDK runs extremely fast and provides all necessary features like testing, creating of the documentation and many more things. The experiences we made in the past showed that working with SDK works very flawless, fast and agile.

### Independent deployment
The SDK is not hard-bound to Shopware version like plugins. This dependency will be included directly in the apps and also supports newer Shopware versions. This allows us to provide separate releases independent of the administration. Example: A small bug can be fixed and released in minutes. A similar deployment in the platform would take much more time.

### SDK is just a convenience layer for the PostMessage API
Similar as the previous reason the SDK is not hard-bound to the Shopware releases and is a completely separate package. And if we would add it into the monorepo we would be bound to the Shopware release cyclus. With a separate repository we can work independent and react faster.

### Independent documentation
Documentation has a really high priority for the SDK. Everything should be documented very well. To make sure that nothing gets merged without documentation we include the documentation in the same repository. Then we can directly see if documentation was written and if not, we wouldn't merge the feature.

### PHP monorepo mixed with JS packages is difficult
The behavior of JS and PHP monorepos is different. Things like nested packages should be avoided in the context of JS. The current platform structure doesn't match the structure of JS monorepos. For example the plugin folder, the component library inside the administration package, etc... This structure leads to difficulties in build systems, dependency resolution behavior (e.g. even if a dependency is not defined in the package.json in can be loaded because it looks traversal for a node_modules folder which can lead to different dependency versions which than can lead to further problems. This already happened in the past in the current component library.) To avoid all these problems at the first place we created a separate repository.

### No need for a monorepo management tool like Lerna
Managing monorepos is not that simple for most developers. We had the experience in the past with Lerna as a monorepo tool. The dependency resolution between packages was not that trivial (also because of the folder structure). This led to broken package-lock files, not working npm installs and many more problems. You need to know how this tooling works to modify things in the separate packages. Even if it is now easier with Yarn or other tools it is still unnecessary complicated.
Working with multi repositories is in this case less error prone. You just do the change in the repository and bump up the version in the package.json. And if you also need the new changes in a different place, then you also need to bump up the dependency version. No magic tooling required for this.

### Monorepo has no real advantage in the SDK case and would make things just more complicated
Monorepos have several advantages over multi repos. But in the case of the SDK almost none of these advantages comes to fruition. Things like deployment, separate testing, documentation, independent versions and many more things would be much more difficult.

## Consequences
If you want to add something to the SDK you need to checkout the GitHub repository and publish the changes to this repository. If the change is also relevant for the administration side - then this version also needs to be bumped up there.




