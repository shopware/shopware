---
title: Implementation of Meteor Component Library
date: 2024-03-21
area: administration
tags: [administration, vue, meteor, component library]
---

## Context

We have decided to implement the Meteor Component Library within the Shopware platform. They will replace the current base components in the administration.

## Decision

To ensure a smooth transition, we have thought of a few things to make the implementation easier. Below we describe the solutions in detail.

### 1. Rename components in the Meteor Component Library

To avoid naming conflicts with the current base components, we will rename the components in the Meteor Component Library. The new components will have the prefix `mt-` (Meteor) in their names. This way we can ensure that the components are easily distinguishable from the current components.

The CSS classes will also be renamed to avoid conflicts with the current CSS classes. The new CSS classes will have the prefix `mt-` (Meteor) in their names.

To avoid breaking changes, we will keep the old exports in the Meteor Component Library. This way we can ensure that existing imports will still work. We will console and warn that using the old components is deprecated. Switching to the new components is recommended and can be done by changing the import path.

### 2. Parallel Usage

For the current major release phase (6.6), we are implementing the Meteor Component Library in parallel with the current base components. Developers will be able to switch between the two libraries using the major feature flag 6.7. This way, we can ensure that current functionality is not affected by the new implementation.

To have both component implementations working at the same time, we will move each component into a "wrapper" component. This wrapper component will decide which component to render based on the feature flag. You can also use the new components directly with the prefix `mt-`.

Example:
```html
<!-- Shopware 6.6 -->

<!-- Is working, emit a warning in console that this component usage is deprecated. -->
<sw-example oldProperty="old">Example</sw-example>
<!-- Is NOT working. -->
<sw-example newProperty="new">Example</sw-example>
<!-- Is working. Uses directly the component from the Meteor Component Library. -->
<mt-example newProperty="new">Example</mt-example>

<!-- Shopware 6.7 -->
<!-- Not working anymore. -->
<sw-example oldProperty="old">Example</sw-example>
<!-- Is NOT working. -->
<sw-example newProperty="new">Example</sw-example>
<!-- Is working. -->
<mt-example newProperty="new">Example</mt-example>
```

### 3. Provide automatic code migration tool

To make the transition as easy as possible, we will provide a code migration tool. This tool will automatically replace the old components with the new ones. The tool will also replace the properties, slot usage, etc. to the new Meteor Component Library. This will save developers a lot of time and make the transition as easy as possible.

We can't guarantee to provide a codemod for every edge case. But the most common use cases will be covered. Developers can also use the codemod as a base and manually modify the code as needed.

### 4. Keeping complicated components in the old implementation

Some components have a lot more differences than others. For example, the `mt-button` component is very similar to the `sw-button` component and can be easily migrated. But the `mt-data-table` component is very different from the `sw-data-grid` component. To avoid breaking changes, we will keep the old implementation of the `sw-data-grid` component with a deprecation note.
This way, we can ensure that the functionality is still available, and developers can migrate to the new component in their own time. We will do this for any component that has a more complicated migration path.

If the manual migration contains breaking changes it have to be done behind a feature flag so that it can be released in a major release.

## Consequences

The implementation of the Meteor Component Library will provide a better developer experience in the long run. We are relying on a single component library, which will make maintenance easier. The components will be more consistent and development will be faster. They will also be more stable, fully tested, accessible and work and look the same as in the Apps. So it is also easier to switch from the plugin system to the app system because they share the same underlying components.

