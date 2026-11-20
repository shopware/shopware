---
title: Make a clone reference its own cloned rows instead of the source's
issue: 22178
---

# Core

* Changed `Shopware\Core\Framework\DataAbstractionLayer\VersionManager::clone()` to point foreign keys between cloned rows at the copies instead of the source's rows, so for example a cloned order's billing address and delivery reference the cloned order's own address.
