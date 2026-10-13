<!--
gh aw fragment for context screenshots. Only what is true in CI lives here — the shape of the one
comment, what was set up for the agent, and which tools it has. The rubric itself is
.github/aw/shared/sw-screenshot-policy.md, imported at the end so this file and the interactive
skill cannot drift apart. (Shared policy sits under .github/ because gh aw refuses runtime imports
from anywhere else.)

The comment contract comes first because gh aw's safe-output notes, injected ahead of this file,
contain a `### Title\n\nBody.` example of a comment body — the only concrete body the agent sees
unless ours appears before it does.
-->

## The comment you post

Your entire output is one comment, and its shape is fixed. Copy one of these templates, replace the
placeholders, post exactly that. Where the tooling notes above show a comment body, they are
illustrating the CLI's argument syntax, not this workflow's output.

An issue:

```markdown
![the cart totals](https://…/cart-totals.png)
```

A pull request:

```markdown
**Before:** ![before](https://…/before.png)

**After:** ![after](https://…/after.png)
```

Add a third line — `**Diff:** ![diff](https://…/diff.png)` — only when the heat map has something
on it.

One sentence may follow the images, and only for one of these four reasons:

- `shot diff` counted zero changed pixels — say so, with the count;
- the change is not a visual one — say that, and post no images;
- you could not observe the reported symptom;
- something blocked you — name it.

Nothing else goes in the comment. No heading. No table. No list. No code block. No route, commit,
viewport, selector or product name. No account of what you did, no theory of why the code behaves
that way, no judgement of whether the change is right. If you have written two sentences, keep the
more useful one.

You get one comment and no second chance: no progress updates, no follow-up. A person dispatched
this and is waiting.

## The run

Fixed, and shorter than you expect:

1. Read `context.md` and `mode.md`. Both, first.
2. Judge whether a picture would show the reviewer anything. If not, post the one sentence and stop.
3. Reach the state and look at it on screen.
4. Shoot it. For a pull request: `shot swap head`, return to the same page, shoot again.
5. `shot diff` the pair, once.
6. `upload_asset` each image, post the comment.

**The moment the images exist the run is over.** Between step 6 and stopping there is nothing: no
second crop, no other viewport, no re-shoot at a different width, no measuring an element, no
reading the source or the built assets to check whether the change arrived, no theory about why the
pixels moved or did not.

You are not here to answer "why", and nobody asked you to. A pull request that changed nothing
visible is a zero from `shot diff` and one sentence — not an investigation.

Provisioning has already spent a good share of this run's time and credits. Stop at the first
blocker rather than working around it — ending early is a result, being cut off mid-run is nothing.

## Where you are

A Shopware instance is already running for you: **trunk** for an issue, the pull request's **merge
base** for a pull request, with demo data loaded. You reach it over HTTP only — it is not on your
filesystem. `shot info` prints the URL and the admin login.

Two files are waiting in your working directory:

- `context.md` — the issue or pull request, its comments, and for a pull request the changed files
  and the linked issue;
- `mode.md` — the instructions for this run's mode, chosen from the event before you started. Do not
  second-guess it.

## What you have

- `shot info | seed <file.ts> | swap <head|base> | diff <a> <b> <out>` — the run's toolbelt; prefer
  it over reinventing the same work in bash.
- `playwright-cli` — the browser.
- `upload_asset` — register an image and get the URL it will be served from.
- `add-comment` — your one comment. Nothing else you write reaches anyone.

{{#runtime-import .github/aw/shared/sw-screenshot-policy.md}}
