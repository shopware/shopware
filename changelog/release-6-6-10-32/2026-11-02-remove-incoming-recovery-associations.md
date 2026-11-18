---
title: Remove incoming recovery associations
---

# API

* Removed the `user.recoveryUser` and `customer.recoveryCustomer` DAL associations.
  Remove criteria using the `user.recoveryUser` or `customer.recoveryCustomer` associations, which were removed in a security update.
  The outgoing `user_recovery.user` and `customer_recovery.customer` associations remain available.
