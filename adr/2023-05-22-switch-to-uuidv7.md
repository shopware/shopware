---
title: Switch to UUIDv7
date: 2023-05-22
area:  core
tags: [DAL]
---

## Context

Using UUIDs as primary keys eases the integration of several different data sources,
but it also brings some performance issues.

Currently, we're using UUIDv4, which is a random UUID the completely random prefix means
that the B-tree indexes of the database are not very efficient.

UUIDv7 time-based prefix is less spread than that of UUIDv4, this helps the database to keep the index more compact.
It allows the Index to allocate fewer new pages and to keep the index smaller.

## Decision

Considering there is little risk to using UUIDv7, as v4 and v7 share the same
length and are indistinguishable for shopware, we can switch to v7 without any risk
of breaking anything.

The effort is also very low as we only need to change the
implementation of the `Uuid` class. As using UUIDv7 will improve the speed of
bulk product inserts by about 8 %, we think the effort is worth the measurable and
theoretical gain.

## Consequences

We will switch to UUIDv7 as default and add performance guides promoting v7.
