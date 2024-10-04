---
title: Export products without manufacturer
date: 2024-10-04
area: administration
tags: [administration, product-export]
---

## Context

Currently twig throws an error if one tries to export a 
product in a compaaring sales channel.

## Decision

To circumvent this, we render an empty string in this case.

## Consequences

There should be no consequence. Currently we don't create a product import. In the
future we create a product export without manufacturer, but this is expected
behaviour if no manufacturer is set.
