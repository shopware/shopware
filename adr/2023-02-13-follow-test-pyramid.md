---
title: Follow test pyramid
date: 2023-02-16
area: product-operations
tags: [test, structure, performance, flakiness]
---

## Context

Since the beginning of the development of shopware 6 we've tried to test as much as possible. Most of the effort went
into writing integration or end-to-end tests. This has led to two main issues

### 1. Performance

E2E tests and integration tests to some degree are slow by nature because they perform a lot of steps to assert 
required conditions. The e2e test suite has grown over the lifetime of shopware 6 to take more than 6 hours of real-time
if executed in serial.

### 2. Flakiness 

Because these tests involve a lot of moving parts, most of these tests are not deterministic and behave a little differently
on every execution. This leads to flakiness, which is sometimes hard to reproduce because it depends on the 
performance/load of the machine that is executing the test.

The flakiness in combination with the number of tests, the performance, the complex test matrix, and the merge trains have 
led to distrust in the test suite and caused a lot of hassle. Especially, when there are many merge requests to be merged,
it's very frustrating for pipelines to fail due to flakiness.

## Decision

We commit to following the best practice of the [test pyramid]. Our testing structure currently is a reversed pyramid. We're using too many
E2E and too few unit tests to test our code base. To get closer to this ideal, we'll cut all E2E tests that can be covered by jest
tests or are better implemented as php integration tests/api tests.

## Consequences

*Coverage will decrease*

- some bugs might slip through that might have been caught by a deleted e2e tests. But this is not very likely  
  because most tests that were deleted do not test features as whole but just mostly admin modules that do crud operations.

*Test pyramid*:
- we have to write more unit tests
- we'll delete all e2e tests that just cover things that can be tested by jest or php integration tests
- we'll write jest tests for basic stuff that was covered by the deleted e2e tests (mostly crud stuff)
- we'll only add E2E tests that actually test an important feature end to end

*Quality*:
- we'll only add high quality E2E tests
- we'll test them thoroughly, before merging them (at least 50 times) 

*Performance*:
- we'll refactor tests to not require a database reset after each test case. We'll also reconsider moving to playwright
- we'll reduce or disable test retries in e2e, to fight performance creep
- we'll move tests into quarantine as fast as possible


[test pyramid]: https://martinfowler.com/articles/practical-test-pyramid.html
