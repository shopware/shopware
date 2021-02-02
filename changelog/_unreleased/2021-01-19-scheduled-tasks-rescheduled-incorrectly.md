---
title: ScheduledTasks rescheduled incorrectly (wrong time)
issue: NEXT-13165
---
# Core
* Changed to rescheduled of `next_execution_time` by `last of next_execution_time + Interval` in `rescheduleTask` method of `src/Core/Framework/MessageQueue/ScheduledTask/ScheduledTaskHandler`
