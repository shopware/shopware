---
title: Added commands to synchronise cart from sql to redis
issue: NEXT-20874 
author: OliverSkroblin 
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added new `cart:migrate` command to synchronise cart between sql and redis
* Added new `EntityDefinitionQueryHelper::columnExists` helper function to check column existence
* Added new `MigrationStep::columnExists` helper function to check column existence
* Added new `cart.payload` column to support cart compressions. This column is used created in a destructive migration
* Added new `SalesChannelContext::getCustomerId` short hand.
