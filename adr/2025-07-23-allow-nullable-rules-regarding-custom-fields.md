---
title: Allow nullable rules regarding custom fields
date: 2025-07-23
area: services-settings
tags: [ rule, abstraction, administration ]
---

## Context

Conditions for the Rule Builder regarding custom fields of type text can not be null.
However the fields themselves can be null, and therefore the rule should be able to handle this.

## Decision

I want to make sure the rules can handle null values regarding text fields,
so I made sure the `CustomFieldRule.php::getConstraints()` method is not attaching a `NotBlank` constraint to these
fields.

## Consequences

The rest of the code is already able to deal with this change, so it should really change anything in that sense.
It just makes sure the rule builder can handle null values in the above-described scenario.
