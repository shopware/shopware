---
title: {{ Add jest runner with disabled compat mode }}
date: {{ 2024-06-12 }}
area: {{ administration }}
tags: [{{ administration, jest, pipeline, testing, unit }}]
---

## Context

Currently, our component tests in Jest are running with enabled compat mode. To remove the compat mode for each
component we need to add a new Jest runner with disabled compat mode to make sure that the tests are running without
compat mode.

## Decision

I added a new runner command in the NPM scripts to run the Jest tests without compat mode. The new runner command is
`unit:disabled-compat` and `unit-watch:disabled-compat`. Also the composer commands are added to run the tests. These commands are `admin:unit:disabled-compat` and `admin:unit-watch:disabled-compat`. These commands are using the environment variable `DISABLE_JEST_COMPAT_MODE` to disable the compat mode.

For the pipeline, I added a new stage to run the Jest tests without compat mode. The stage is `Jest (Administration with disabled compat mode)`.

To mark a test file working without the compat mode you need to add a comment with the `@group` tag. The tag is `@group disabledCompat`.

Example:
```javascript
/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
...
```

With this tag, the test file is running without compat mode. To make a component working for both modes, you can use the
compatUtils helper function from Vue compat:
```javascript
// Example
import { compatUtils } from '@vue/compat';

...

if (compatUtils.isCompatEnabled('INSTANCE_LISTENERS')) {
    return this.$listeners;
}

return {};

...
```


Important: the test still runs also with compat mode activated in parallel.

## Consequences

- Fixing the components and tests to run also without compat mode. This has to be done by removing the compat mode for each component.
- Marking fixed tests with `@group disabledCompat` to run without compat mode.
- When everything is fixed, we can remove the compat mode from the Jest configuration.
