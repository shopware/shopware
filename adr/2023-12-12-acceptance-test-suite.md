---
title: New acceptance test suite
date: 2023-12-12
area: quality
tags: [testing, acceptance testing, E2E, playwright]
---

## Context
For our ambitions to consequently reduce manual testing in favour for test automation the current E2E test suite, based on Cypress, is not sufficient anymore. It has several flaws that have become a big road-blocker. The test suite is tightly coupled to the state of the test environment, which leads to tests that are not very deterministic and tend to be slow and flaky. Tests are often created in different ways and don't follow a specific strategy. In addition, the test suite is currently not able to test against our cloud environment.

Our goal is to create a test suite that fulfills the following requirements:

*  Deterministic tests that can run against any environment.
*  Fast and reliable tests that follow a certain test strategy.
*  Tests that are derived from real product requirements and validate behaviour.
*  A test framework that is easy to learn and using a readable syntax that also non-tech people can comprehend.

## Decision
In order to achieve this goal we decided to not further invest time in the existing test suite, but start from scratch. This offers the opportunity to also switch to another test framework that better fulfills our needs. 

After some evaluation we chose Playwright as the new test framework. First benchmarks have shown that it is faster and more stable than Cypress. Additionally, it is easy to learn, has very good documentation and uses a syntax that is easily to understand. It also offers the functionality of creating reusable traces, which come in handy when debugging failing tests from pipelines.

## Consequences
We will stop our efforts on the existing E2E test suite based on Cypress and start a new acceptance test suite based on Playwright. The two test suites will co-exist for a certain amount of time until we have created the necessary test coverage with the new test suite. Tests are not just simply transferred from the old to the new test suite, but are also rethought based on a proper test strategy and the defined goals.

The new test suite will be developed in `tests/acceptance`. Please have a look at the `README.md` for more information about the test suite.
