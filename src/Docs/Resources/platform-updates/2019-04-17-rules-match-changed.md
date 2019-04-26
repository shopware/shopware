[titleEn]: <>(Refactored rules match function)

The Rules were refactored, so that the match function not longer returns a reason object which contains the debug messages. Instead the match function directly returns a bool if the rule is matching or not.
