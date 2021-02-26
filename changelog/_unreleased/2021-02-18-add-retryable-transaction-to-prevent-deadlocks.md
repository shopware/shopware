---
title: Adds the RetryableTransaction to prevent database deadlocks
issue:
author: Hannes Wernery
author_email: hannes.wernery@pickware.de
author_github: hanneswernery
---
# Core
* Added class `src/Core/Framework/DataAbstractionLayer/Doctrine/RetryableTransaction.php` that automatically retries a
  transaction if it failed because of a database deadlock or lock wait timeout.
* Deprecated constructor argument usage `(Doctrine\DBAL\Driver\Statement $query)`
  in `src/Core/Framework/DataAbstractionLayer/Doctrine/RetryableQuery.php`. Use arguments
  `(Doctrine\DBAL\Connection $connection, Doctrine\DBAL\Driver\Statement $query)` instead.
* Deprecated argument usage `(\Closure $closure)` in static function `retryable` in
  `src/Core/Framework/DataAbstractionLayer/Doctrine/RetryableQuery.php`. Use arguments
  `(Doctrine\DBAL\Connection $connection, \Closure $closure)` instead.
___
# Upgrade Information
If multiple `RetryableQuery` are used within the same SQL transaction, and a deadlock occurs, the whole transaction is
rolled back internally and can be retried. But if instead only the last `RetryableQuery` is retried this can cause all
kinds of unwanted behaviour (e.g. foreign key constraints).

With the changes to the `RetryableQuery`, you are now encouraged to pass a `Doctrine\DBAL\Connection` in the constructor
and the static `retryable` function. This way, in case of a deadlock, the `RetryableQuery` can detect an ongoing
transaction and may rethrow the error instead of retrying itself.

#### Old usages (now deprecated):
  ```php
  $retryableQuery = new RetryableQuery($query);
  
  RetryableQuery::retryable(function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```

#### New usages:
  ```php
  $retryableQuery = new RetryableQuery($connection, $query);
  
  RetryableQuery::retryable($this->connection, function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```

If you are knowingly using a SQL transaction to execute multiple statements, use the newly added `RetryableTransaction`
class. With it the whole transaction can be retried in case of a deadlock.
#### Example usage
  ```php
  RetryableTransaction::retryable($this->connection, function () use ($sql): void {
      $this->connection->executeUpdate($sql);
  });
  ```
