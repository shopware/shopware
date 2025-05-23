---
title: Added new increment api to delete unused keys or cluster
---
# Core
* Added new public method `delete` in `\Shopware\Core\Framework\Increment\AbstractIncrementer::delete` and its implementations
___
# API
* Added new API `DELETE /api/_action/delete-increment/{pool}` to delete unused keys or cluster.