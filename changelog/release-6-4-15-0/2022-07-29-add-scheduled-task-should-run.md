---
title: Add scheduled task should run
issue: NEXT-22672
---

# Core

* Added new method `shouldRun` to `Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask`
  * This method is called before the task will be scheduled and can be used to cancel the scheduling
