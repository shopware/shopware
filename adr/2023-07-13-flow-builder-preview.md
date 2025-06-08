---
title: Flow Builder Preview
date: 2023-07-13
area: Business Ops
tags: [services-settings, flow]
---

## Context
In the past merchants had to deal with issues where their custom-built flow did not
behave how they intended it to do. An concrete example: We've had a merchant that contacted
us (shopware) that their shop did not sent out mails. Debugging the flow turned out to
be harder than we thought and honestly harder than it should be. The flow builder should
empower users to build reliable flows and not spend their precious time trying to
figure out what went wrong.

To improve the experience when using the Flow Builder were taking measures. First,
Flow Builder Preview and second Flow Builder Logging. This ADR only discusses the former one,
there are plans internally for the latter one, but it won't get much attention for now, as
it also addresses different issues (what does my flow do vs what went wrong in the past).

Users should be able to preview a flow and get further understanding on how a flow executes.
This preview only displays the steps and decisions happening inside a flow but doesn't
execute / simulate the real flow

## Decision
The whole scope of the Flow Builder Preview is to help merchants *creating* new flows and
*update* their existing flows.

Will do:
* Evaluate path of flow by executing rules
* Validating the input data of the actions
* Leave it up to the action how much “real” code is actually executed

Won't do:
* Executing Flow Builder Actions i.e. calling the `handleFlow` method of an action
* Execute one huge transaction to the database and rollback later
    * Mail actions e.g. could render the mail template and send it to a given mail address
    * CRUD actions e.g. could decide, if they only want to check the input data or
      open a transaction to roll it back

### Preview is optional / We and Third Parties can provide this functionality
It is important to note that with this change existing and new actions do *not* need
to implement the "preview" functionality and implementing it is completely optional.
Actions that do not implement the new feature will be marked as "skipped" inside
the administration.

### A new core interface
The core interface defines the data structure of the output and third party developers
could use this to make the flow action previewable. The interface could look like this:

```php
interface Previewable
{
    public function preview(...): PreviewResponseStruct
}
```

Flow actions are responsible to implement this interface if they want to and execute the
necessary steps to generate a preview without actually writing / executing anything real.

### Separation from Flow Logging
The Flow Builder Preview only addresses half of merchants pain points. While it does
help with the creation of new flow it does not help merchants to manage incidents in
their flows. This is where the Flow Logging feature comes in hand.

## Consequences
Though, it is completely optional to implement the "preview" feature for an action,
we advice developers to do so. Doing, this will benefit...
1. ... the merchant when previewing their flow,
2. ... the plugin developer because the values the action provides increases

Because, the execution of the flow in a preview mode behaves different compared to
when it is actually being executed, developers should make sure that the implementation
their action preview stays as close as possible to the implementation of their action.
Otherwise the preview could present a wrong impression to the merchant
