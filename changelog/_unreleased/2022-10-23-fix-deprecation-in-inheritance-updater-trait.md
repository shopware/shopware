---
title: Fix use of deprecated Connection::executeUpdate in InheritanceUpdaterTrait
author: Johannes Przymusinski
issue: NEXT-23855
author_email: johannes.przymusinski@jop-software.de
author_github: cngJo
---
# Core
* Connection::executeUpdate is deprecated, use the replacement Connection::executeStatement
