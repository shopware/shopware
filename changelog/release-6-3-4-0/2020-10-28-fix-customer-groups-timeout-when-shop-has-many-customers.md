---
title: Fix customer groups timeout when shop has many customers
issue: NEXT-9784
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Removed associations `salesChannels` and `customers` in computed `allCustomerGroupsCriteria` in the component `sw-settings-customer-group-list` 
* Removed associations `salesChannels` and `customers` in computed `customerGroupCriteriaWithFilter` in the component `sw-settings-customer-group-list` 
___
# Upgrade Information

## Removed associations in customer group criteria
We have to remove the associations `salesChannels` and `customers` 
in these computed properties: `allCustomerGroupsCriteria` and `customerGroupCriteriaWithFilter`
which can be find in this component: `sw-settings-customer-group-list`.

The reason for this is that a shop with many customers can´t open the module. The response
is too heavy because all customers in the shop will be loaded. This can lead to a response 
timeout.

When you need the customer information then it would be good to fetch them in your plugin.
You should use a criteria object which only fetches a limited amount of customers.
