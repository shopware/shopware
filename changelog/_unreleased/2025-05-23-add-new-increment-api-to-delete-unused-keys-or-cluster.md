---
title: Added new API to delete unused increment keys or cluster
issue: #9826
---
# Core
* Added new public method `delete` in `\Shopware\Core\Framework\Increment\AbstractIncrementer::delete` and its implementations
___
# API
* Added new API `DELETE /api/_action/delete-increment/{pool}` to delete unused keys or cluster.
___
# Administration
* Added new `deleteActivityKeys` method in `src/core/service/api/user-activity.service.js` to delete unused keys.
* Changed `getFrequentlyUsedModules` in `sw-search-bar` component to delete unused keys.
