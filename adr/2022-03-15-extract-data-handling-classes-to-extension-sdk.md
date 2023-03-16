---
title: Extract data handling classes to extension sdk
date: 2022-03-15
area: administration
tags: [admin, extension-api]
--- 

## Context
* The package `@shopware-ag/admin-extension-sdk` will be referred to as sdk
* The ts/js implementation of the Administration is referred to as administration

Previously the administration held the implementation of the classes `Entity`, `EntityCollection` and `Criteria`.
This led to the problem, that the sdk was unable to identify instances of this classes easily.
Since the administration is not a standalone package which could be imported in the sdk.
Also, the sdk would need to copy the implementation since we want to copy the administration datahandling in the sdk.

## Decision
Move the implementation of `Entity`, `EntityCollection` and `Criteria` to the sdk.
The corresponding files in the administration simply forward the default export of the sdk.

## Consequences
This will result in the same behaviour for current implementations.
On the other hand it provides the benefit of having these basic classes in an external package anybody can use.
