---
title: Admin extension API standards
date: 2021-12-07
area: administration
tags: [plugin, admin, extension-api]
---

## Context
We need to provide ways for extension developers to add custom components and views to different places in the administration. Multiple solutions where discussed and tested, this ADR contains a summary of the final solution.

## Decision

### Word definitions
For a better understanding of the following text it is good to have a definition for specific words:

#### Location
Extensions can render custom views with the Admin-Extension-API via iFrames. To support multiple views in different places every "location" of the iFrame gets a unique ID. These can be defined by the app/plugin developer itself.

*Example:*  

An app wants to render a custom iFrame in a card on the dashboard. The "location" of the iFrame has then a specific "locationID" like `sw-dashboard-example-app-dashboard-card`. The app can also render another iFrames which also get "locationIDs". In our example it is a iFrame in a custom modal: `example-app-example-modal-content`.

The app want to render different views depending on the "location" of the iFrame. So the app developer can render the correct view depending on the "locationID":
```js
if (sw.location.is('sw-dashboard-example-app-dashboard-card')) {
    renderDashboardCard();
}

if (sw.location.is('example-app-example-modal-content')) {
    renderModalContent();
}
```

#### PositionID (PositionIdentifier)
Developers can extend existing areas or create new areas in the administration with the Admin-Extension-API. To identify the positions which the developer want to extend we need a unique ID for every position. We call these IDs "positionID".

*Example:*

An app wants to add a new tab item to a tab-bar. In the administration are many tab-bars available. So the developer needs to choose the correct "positionID" to determine which tab-bar should be extended. In this example the developer adds a new tab item to the tab-bar in the product detail page.
```js
sw.ui.tabs('sw-product-detail').addTabItem({ ... })
```

### Solution:
We use the concept of component sections for providing injection points for extension components.

#### Component Sections

In most cases developers will directly use the extension capabilities of the UI components (e.g. adding tab items, adding button to grid, ...). This will cover most needs of many extensions.

To give them more flexibility we introduce a feature named "Component Sections". These are sections where any extension developer can inject components. These components are prebuilt and they can also contain custom render views with iFrames. The developer needs to use the feature and choose the matching positionID for the component position. 

```js
// Adding a card before the manufacturer card with custom fields entries.
sw.ui.componentSection('sw-manufacturer-card-custom-fields__before').add({
    // The Extension-API provides different components out of the box
    component: 'card', 
    // Props are depending on the type of component
    props: {
        title: 'This is the title',
        subtitle: 'I am the subtitle',
        // Some components can render a custom view. In this case the extension can render custom content in the card.
        locationId: 'example-app-card-before-manufactuer-custom-fields-card'
    }
})
```

#### Vue Devtools Plugin for finding the PositionIDs
It is impossible to create a list of all potential position IDs. And they would be hard to manage. To solve this problem we are writing a custom plugin for the Vue Devtools. This plugin will be available for Vue Devtools 6+. It makes identifying the position IDs very easy.

Just open the plugin in the Devtools (It is available directly when you open the Administration). Then you can see all positions at the current administration view which are available for extending. If you click at one position ID you get more information about it. Like the property in the Meteor-Extension-SDK so that you directly know what functionality this position has.

In summary: the Devtool plugin provides a visual way to see which parts can be extended and what are the positionIDs for the extension position.

## Consequences
We need to implement the componentSectionRenderer to positions where we want to provide an extension position for apps and plugins. These can be positions like before or after cards, at the top or bottom of a page, at the top or bottom of a tab view and many more.
