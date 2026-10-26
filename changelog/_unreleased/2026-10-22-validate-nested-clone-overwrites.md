---
title: Validate nested clone overwrites
author: Jonas Elfering
author_email: j.elfering@shopware.com
author_github: keulinho
---

# API

* Changed clone requests to validate explicit overwrites with DAL field protection and nested create/update permissions in the caller's scope, preserving association payloads, null suppression and copied protected values.
