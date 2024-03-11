---
title: Merge E2E projects into a single project
date: 2021-11-22
area: core
tags: [e2e, cypress]
---

## Context

It's hard to test components in isolation. Other components are almost always also tested, which is intended because it's the nature of end-to-end tests being workflow-based.

There are currently three E2E projects that are maintained separately. There are a lot of duplicated commands and different variations of them.

## Decision

We'll merge all cypress e2e projects of platform into a single project.

The projects will be merged by

- creating new project `E2E` in `tests/E2E`
- moving storefront tests to `tests/E2E/cypress/integration/storefront`
- moving administration tests to `tests/E2E/cypress/integration/administration`
- moving recovery tests to `tests/E2E/cypress/integration/recovery`
- moving the new package test scenarios to `tests/E2E/cypress/integration/scenarios`
- merging the commands.js files and removing duplicate code
- merging the setup code
- merging fixtures
- use automatic cleanup in global setup instead of manual calls to `cleanUpPreviousState` in admin tests

## Consequences

The command and support code are shared by all tests, therefore the ownership of the project itself is now shared among the component teams.
The tests themselves should be written and maintained by the solution teams.

The commands to run the e2e tests, and the pipelines need to be updated.
