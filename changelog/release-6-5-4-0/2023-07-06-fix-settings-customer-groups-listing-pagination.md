---
title: Fix settings customer groups listing pagination
issue: NEXT-26862
---
# Administration
* Changed component `sw-entity-listing` in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-list/sw-settings-customer-group-list.html.twig` to add `criteria-limit`.
* Changed method `customerGroupCriteriaWithFilter` in `sw-settings-customer-groups-list` component to change the limit in the criteria.
