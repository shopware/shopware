---
title: Fix unprepared statements breaking profiler
author: Melvin Achterhuis
author_email: melvin@achterhuis.work
author_github: @MelvinAchterhuis
---
# Core
* Added `convert_encoding` Twig filter before escaping so it's guaranteed UTF-8 in `Core/Profiling/Resources/views/Collector/db.html.twig`
