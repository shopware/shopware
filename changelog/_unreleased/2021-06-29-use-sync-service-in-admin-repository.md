---
title: Use sync service in admin repository
issue: NEXT-11927
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed signature of `EntityWriterInterface::sync`, the function returns now a `\Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult`  
___
# Administration
* Changed internal requests of `repository.data.js`. The repository uses now the `/_action/sync` endpoint to commit all changes in a single transaction.
