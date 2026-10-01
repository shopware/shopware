---
title: Split large Administration test files
date: 2026-05-06
area: administration
tags: [admin, test, jest, eslint]
---

## Context
Administration unit test files often grow beyond 500 lines. Large test files make the tested scenarios harder to scan, increase merge conflicts, and make focused test execution less convenient.

Tests are colocated next to the source file they cover. That convention still works for small test suites, but it does not provide a clear structure once a single source file needs many scenario groups, fixtures, builders, or mocks.

## Decision
Administration test files above 500 lines should be split into smaller spec files. The split test directory uses the source file name with a `.spec` suffix, and each executable test inside the directory also uses the `.spec.js` or `.spec.ts` suffix.

Example:

```text
src/module/sw-import-export/component/sw-import-export-activity/
  index.js
  sw-import-export-activity.html.twig
  sw-import-export-activity.scss
  sw-import-export-activity.spec/
    export-activities.spec.js
    import-activities.spec.js
    activity-actions.spec.js
    profile-modal.spec.js
    fixtures.js
```

Jest discovers both colocated single-file specs and split specs in `.spec` directories. The Administration baseline test treats a `.spec` directory with at least one `*.spec.js` or `*.spec.ts` file as test coverage for the corresponding source file.

ESLint warns when an Administration test file reaches 500 lines and errors when it reaches 1000 lines.

## Consequences
New large test suites can be organized by behavior instead of being forced into a single file. Helper files in `.spec` directories, such as fixtures or builders, are allowed but are not executed as tests unless they use the `*.spec.js` or `*.spec.ts` suffix.

Developers should prefer splitting a test file once it crosses 500 lines. Files above 1000 lines are considered too large for new or migrated specs.
