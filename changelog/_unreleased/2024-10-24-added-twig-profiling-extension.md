---
title: Added twig profiling extension
issue: 
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---
# Core
* Added `{% profile #name# %}` and `{% endprofile %}` twig nodes to start and stop the profiles within twig rendering
* Added new `{{ profiler_start }}` and `{{ profiler_stop }}` twig functions to start and stop the profiles within twig rendering 
