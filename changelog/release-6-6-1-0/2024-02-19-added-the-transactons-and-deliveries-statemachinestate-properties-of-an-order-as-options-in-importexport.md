---
title: Added the "transactions" and "deliveries.stateMachineState" properties of an order as options in Import/Export
issue: NEXT-21544
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Added additional serializing in `OrderSerializer` for the order properties `transactions`, `transactions.stateMachineState` and `deliveries.stateMachineState`
___
# Administration
* Added additional processing of `transactions` properties in `sw-import-export-entity-path-select`
