---
title: Fix mariadb compatibility
issue: NEXT-29992
---

# Core

* Changed migration `Shopware/Core/Migration/V6_4/Migration1656397126AddMainVariantConfiguration` to drop the foreign key constraint before creating a generated column to bypass a new MariaDB limitation
