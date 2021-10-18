---
title: Prevent deleting active addresses via store-api
issue: 2117
author: James Joffe
author_github: drjamesj
---
# API
* Changed `DELETE /store-api/account/address/{addressId}` to throw an error if attempting to delete an active address
