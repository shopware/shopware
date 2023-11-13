---
title: Test structure
date: 2022-10-20
area: administration
tags: [test, structure]
--- 

## Context
Currently, all tests are in the same folder: `src/Administration/Resources/app/administration/test`.
This approach has some disadvantages:
- While changing a component, you have to search for the corresponding test file
- You can't see which components are tested and which are not

## Decision
We will move the tests to the same folder as the components they test.
This approach is standard in the Vue community and solves the problems mentioned above.

### Example
The test for the `sw-cms-el-config-image` component will be moved to `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-el-config-image/sw-cms-el-config-image.spec.js`.
The test file should contain the same name as the component it tests.
So a valid test file name would be `[component name].spec.js|ts`.
The `[` and `]`are not part of the name. Additionally test files are either `.js` or `.ts` files.
Typescript is preferred.

## Consequences
Test files will no longer be loaded from the test folder.
