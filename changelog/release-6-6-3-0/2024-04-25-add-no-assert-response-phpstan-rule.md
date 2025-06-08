---
title: Add `NoAssertsOnResponseObjectsRule`
issue: NEXT-35602
---
# Core
* Added `NoAssertsOnResponseObjectsRule` to prevent using `assert` on response objects, as those comparisons are always flaky, because of datetime in the header.
* Added `AssertResponseHelper` for cases where asserts on responses make sense, and that helper removes the datetime from the header before comparing the responses.
