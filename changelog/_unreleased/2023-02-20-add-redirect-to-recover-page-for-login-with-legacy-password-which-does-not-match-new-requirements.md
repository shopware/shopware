---
title: Add redirect to password recover page for login with legacy password which does not match new requirements
issue: X
author: Sven Mäurer
author_email: s.maeurer@kellerkinder.de
author_github: Zwaen91
---
# Core
* Throw `PasswordPoliciesUpdatedException` in `AccountService` if the legacy password cannot be auto migrated based on new requirements.
___
# Storefront
* Redirect to password recover page and show a message if a `PasswordPoliciesUpdatedException` is thrown.
