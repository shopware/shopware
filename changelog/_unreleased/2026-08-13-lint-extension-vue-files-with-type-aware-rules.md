---
title: Lint extension .vue files with the same type-aware rules as .ts
issue: #19296
author: Gerrit Weiermann
author_email: g.weiermann@shopware.com
---
# Administration
* Changed the Administration extension tooling (`composer admin:check-extensions`) to lint extension `.vue` single-file components with the same type-aware ESLint rules as `.ts` files, including `@typescript-eslint/no-deprecated`, `@typescript-eslint/no-floating-promises` and `no-unused-vars`.
* Changed the bundled Administration lint toolchain to `eslint-plugin-vue` 10 and `vue-eslint-parser` 10. Extensions keep resolving these from the installed Administration and do not install them themselves.
___
# Upgrade Information
## Type-aware linting for extension `.vue` files

The extension tooling previously skipped the type-aware ESLint rules and `no-unused-vars` on `.vue` files. It now lints them like `.ts` files, so `composer admin:check-extensions` can report new findings in your `.vue` components — for example calls to `@deprecated` members, unhandled (floating) promises, and unused `<script setup>` bindings.

The `@typescript-eslint/no-unsafe-*` rules stay off on `.vue`, matching the `@vue/eslint-config-typescript` default, because the single-file-component type information is not complete enough for them.

Fix the reported findings, or adjust the rules in your extension's own committed ESLint config.
