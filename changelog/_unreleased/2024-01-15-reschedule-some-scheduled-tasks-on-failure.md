---
title: Reschedule some scheduled tasks on failure
issue: NEXT-32238
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Added new method `shouldRescheduleOnFailure` to scheduled tasks. By default, tasks are not rescheduled on failure. This can be changed by implementing the new method in the task class.
* Added compiler pass to register scheduled tasks so that the attribute `AsMessageHandler` is taken into account.
