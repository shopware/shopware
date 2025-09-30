---
title: Add div wrapper support for extensions api
---
# Administration
* Added support for rendering the extension iframe inside a html div element using component sections.

```
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.componentSection.add({
    component: 'div',
    positionId: 'sw-help-sidebar__navigation',
    props: {
    locationId: 'my-custom-location-id',
    },
  });
```
