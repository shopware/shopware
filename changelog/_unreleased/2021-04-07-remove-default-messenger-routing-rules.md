---
title: Remove default messenger routing rules
issue: NEXT-12870
flag: FEATURE_NEXT_12870
---
# Core
* Removed default `framework.messenger.routing` rules. This makes it now possible to send a message to a transport without also sending it to the default transport. 
* Added class `DefaultSenderLocator`
* Changed message queue statistic handling to only track messages sent or received by the default transport
* Changed `ScheduledTask` so that it's possible to run them outside a schedule
___
# Upgrade Information
## Default messenger routing

We've removed the default routing rules, because it made it impossible to send a messages to transports without also 
sending it to the default transport.

This is now handled by `DefaultSenderLocator` which sends it to the DefaultTransport (alias: `default`) only if no
routing rule has matched and no sender was found. 
