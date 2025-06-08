---
title: Fix logging of exceptions
issue: NEXT-14520

 
---
# Core
* The priority of the ResponseExceptionListener has been lowered to -1 to prevent sending the error response before other listeners have been run
