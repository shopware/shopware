---
title: Update outdated default tax rules
---

# Core

* Added migration `Core\Migration\V6_6\Migration1794852779UpdateDefaultTaxRules`, which changes the default tax rules of Czechia, Estonia, Finland, Lithuania, Romania and Slovakia to the current VAT rates and adds a 5% rule for "Reduced rate 2" in Slovakia and Slovenia. Only rules of the default tax groups that still have their original rate and were never edited are changed. Shops that adjusted these rules themselves keep their rules, and should compare them with the current rates.
