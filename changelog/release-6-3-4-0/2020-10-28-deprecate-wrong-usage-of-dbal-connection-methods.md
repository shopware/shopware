---
title: Deprecate wrong usage of DBAL connection methods
issue: NEXT-11630
---
# Core
* Deprecated usage of `Doctrine\DBAL\Connection::executeQuery` for `UPDATE|ALTER|BACKUP|CREATE|DELETE|DROP|EXEC|INSERT|TRUNCATE` operations in migrations. Use `Doctrine\DBAL\Connection::executeUpdate` instead.
___
# Upgrade Information
## Usage of DBAL connection methods in migrations
For compatibility with main/replica database environments and blue green deployment,
it is important to use the correct methods of the DBAL connection in migrations.
Use `Doctrine\DBAL\Connection::executeUpdate` for these operations: `UPDATE|ALTER|BACKUP|CREATE|DELETE|DROP|EXEC|INSERT|TRUNCATE`
For everything else `Doctrine\DBAL\Connection::executeQuery` could be used.
Using `executeQuery` for the mentioned operations above is deprecated and will throw an exception with Shopware 6.4.0.0.
