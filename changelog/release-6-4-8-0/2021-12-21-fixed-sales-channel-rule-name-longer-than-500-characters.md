---
title: Fixed sales channel rule name longer than 500 characters
issue: NEXT-19317
---
# Core
* Changed logic to generate sales channel rule to make sure the new rule name no longer than 500 characters and create new migration `Migration1639992771MoveDataFromEventActionToFlow` handle fail case if any.
