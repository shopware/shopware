---
title: Remove incoming recovery associations
---

# API

* Removed the `user.recoveryUser` and `customer.recoveryCustomer` DAL associations.

___

# Core

* Deprecated `UserEntity::getRecoveryUser()`, `UserEntity::setRecoveryUser()`, `CustomerEntity::getRecoveryCustomer()`, and `CustomerEntity::setRecoveryCustomer()` because the associations were removed.
* The outgoing `user_recovery.user` and `customer_recovery.customer` associations remain available.
