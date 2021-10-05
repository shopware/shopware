---
title: Improved data consistency
issue: NEXT-15805
author: Ulrich Thomas Gabor
author_email: ulrich.thomas.gabor@odd-solutions.de
author_github: UlrichThomasGabor
---
# Core
* All calls to `beginTransaction` have been replaced with the new `RetryableTransaction` class, as it provides a cleaner interface anyway and the previous code did not execute `rollback` on exceptions.
* `MultiInsertQueryQueue` now uses `RetryableTransaction` as well as there is no use case where it is desirable that half of the inserts is in the DB and then the execution of the script stops and the other half is left in the wide nothingness.
* Introduced transactions at multiple code positions to improve data consistency. Also removed all attempts to be smarter than the DBMS in case of a deadlock and try non-batchy execution of queries; executing single statements not within a transaction can lead to data inconsistency; executing single statements inside of a transaction is in no case beneficial to a batch statement. Catching exceptions also breaks the transaction control flow, i.e. they are not reverted correctly.
