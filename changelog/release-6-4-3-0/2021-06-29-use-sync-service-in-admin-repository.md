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
* Added option `useSync: [bool]` to `repositoryFactory.create`, which causes the repository to store the data via the sync service 
