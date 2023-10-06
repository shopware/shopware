---
title: Add Primary replica config
issue: NEXT-16074
---

# Core
* Added new environment variable `DATABASE_REPLICA_0_URL` to configure primary replica MySQL connection
  * To add multiple replicas use `DATABASE_REPLICA_0_URL`, `DATABASE_REPLICA_1_URL`, etc.
