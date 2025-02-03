---
title: Add compare Shopware Version to meteor SDK
issue: NEXT-36291
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @tajespasarela
---
# Administration
* Added new compare Shopware Version to meteor SDK
___
# Upgrade Information

The context from the meteor SDK now has a new method `compareShopwareVersion` that allows you to compare the current Shopware version with a given version.

## Example

```typescript
import { context } from '@shopware-ag/meteor-admin-sdk';

const isRightVersion = await context.compareShopwareVersion({version:'6.4.0', comparator: '>='});
```
