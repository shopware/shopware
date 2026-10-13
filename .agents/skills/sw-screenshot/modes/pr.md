# Mode: pull request

One shop, one database, two refs. It starts on the pull request's **merge base**; `shot swap head`
moves it to the **head**. Everything in issue mode applies — you do it twice, and the second time
the code has changed under you.

## The target

A before-and-after pair: the described state on the merge base, then the same state with the pull
request applied.

## Where the repro comes from

You have no state; you reach it exactly as in issue mode. Reproduction steps are in the pull request
body or in the linked issue — both are in `context.md`. **If neither is enough, stop and ask the
author for them.** Do not infer a route from the changed files and do not photograph a page you
merely suspect is relevant. An unfounded before-and-after is worse than none.

## The swap

`shot swap head` runs outside your sandbox: the command hands over a request, the host fetches the
head, migrates the database, rebuilds what the diff touched and waits for the shop to answer. It
takes minutes. Let it.

Seeded data survives the swap; only the source tree moves. Do not seed again.

## Capture the pair identically

Same viewport, same element, same scroll position, both times — without that the heat map is
impossible. You have to reach the state twice, so write a small Playwright script you can re-run
rather than retrace a click path from memory.

## If the swap fails

`shot swap` exits non-zero and prints why. Post the before and one sentence saying the after could
not be produced. One labelled screenshot beats an abandoned run.

**Do not manufacture the after.** Not by editing built CSS or JavaScript, not by patching styles in
the browser, not by applying "the equivalent change" by hand. Those show what you expected the diff
to do, not what it does — the one thing the reviewer cannot get from reading the diff themselves.
The swap is the only route to an after.

## The admin rebuild

When the diff touches any bundle's `Resources/app/administration`, the swap rebuilds the whole
Administration. That is several minutes of the budget; plan the rest of the run around it.
