# 2021-06-14 - Introduce jest-fail-on-console

## Context
A jest pipeline run produced previously hundreds of errors and warnings, which made it hard to see why a test failed and if a passing test isn’t just a false positive.

## Decision
To combat this we decided to introduce the npm package [jest-fail-on-console](https://github.com/ricardo-ch/jest-fail-on-console#readme), which causes individual unit tests to fail, if they log an error or a warning to the console.

## Consequences
Jest-fail-on-console makes unit tests a lot more expressive, because it prevents easy mistakes, which would previously lead to an error that is hard to find and notice. Like an incorrect key in a `v-for` loop, which could potentially lead to vue update errors, but would have not caused the test to fail.

Jest tests might be a little harder to write, because errors cannot simply be ignored anymore. All needed components have to be provided to the component being tested either by being mocked or built, all API requests need to be mocked and all needed mixns have to be provided. Although it is a little more work, it makes the jest tests, as previously mentioned, more expressive and as a neat side benefit it keeps the console clean.
