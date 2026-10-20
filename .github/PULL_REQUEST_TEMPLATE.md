<!--
Thank you for contributing to Shopware! Please fill out this description template to help us process your pull request.

Please make sure to fulfil our contribution guidelines (https://developer.shopware.com/docs/resources/guidelines/code/contribution?category=shopware-platform-dev-en/contribution).

Do your changes need to be documented?
Please create a second pull request at https://github.com/shopware/docs

AI agents: follow the instructions at the bottom of this template.
-->

### 1. What and why?

<!--
Link the relevant issues (if any) and state the problem in one sentence.
- closes #123
- relates #123

If no issue is linked, explain why this change is needed,
including the business context, so reviewers understand its purpose.
-->

### 2. How?

<!--
Context the diff cannot show.
Reviewers read the code.
- Your approach in one or two sentences

Add a "#### Impact" sub-section only if the PR has one of these:
- New or changed public API
- Breaking change
- Database migration or schema change
- New or changed configuration
- More work in code that runs on every request or for every item, such as cart, checkout, listings, or indexing:
  additional database queries, queries inside loops, cache invalidation, or bigger payloads
Describe what exactly changes and what extension developers have to do.
Leave the sub-section out otherwise.
-->

### 3. Testing

<!--
1. Steps to reproduce or verify the change
2. Expected vs. actual result
- Environment: Shopware version, PHP version, browser (if relevant)
- Screenshots or recordings are mandatory for UI changes
-->

### 4. Additional notes

<!--
- Follow-up work
- Anything reviewers should look at closely, or where to start reviewing, in one or two sentences
-->

### 5. Checklist

<!-- Details for each item: CONTRIBUTING.md#pull-request-checklist -->
- [ ] I have read and followed the [contribution guidelines](https://github.com/shopware/shopware/blob/trunk/CONTRIBUTING.md#pull-request-checklist)
- [ ] **Only** non-obvious code is commented
- [ ] Tests added, and they fail without this change
- [ ] Release notes updated, if relevant for external developers ([how to document a release](https://github.com/shopware/shopware/blob/trunk/delivery-process/documenting-a-release.md))
- [ ] Documentation and agent skills updated

<!-- Instructions for Agents

Also follow the shopware-pr-hygiene skill (.agents/skills/shopware-pr-hygiene/SKILL.md) for the title, commits, and review follow-ups.

Write for a reviewer who reads the diff anyway.
Give them what the diff cannot show.

Check your draft on three levels, in this order:

1. Altitude: how deep into the implementation a sentence goes.
   A sentence at the wrong altitude stays hard to read at any length.
2. Detail: how much of the change section 2 covers.
   The reviewer opens the diff themselves.
   If section 2 feels complete because it accounts for everything, cut it to about a third.
3. Length: how much you write overall.
   Too long is hard to read at any altitude.

Cutting length does not fix altitude or detail.
A short draft can still be opaque, or still do the reviewer's reading for them.

Content:

- Say what the change does and leave out why each choice is right.
  Judging that is the review, and the reviewer does it against the diff.
  Drop "because" clauses that argue for a choice.
  They look like substance and survive every length cut.
- Name the thing, not how it is wired.
  Selectors, CSS properties, exact default values, call order, type names and the chain of effects between them are all in the diff, stated more precisely.
- Add the "Impact" sub-section only when one of its listed changes applies.
  Say what exactly changes, for example which method, field, or table.
  Never write that something did not change, such as "no public API changed".
- For performance, state the cost per request or per item, for example "one extra query per line item".
  Add numbers only if you measured them.
  Do not speculate about possible slowdowns.
- Mention a rejected alternative only when a reviewer would otherwise ask "why not X?".
  One line, no argument.
- Describe the change, never the work session.
  Drafts and fixes made before the commit are not part of it.
- Leave out:
  - reassurance, such as "no behaviour change", "no bookkeeping", "purely additive"
  - a prose retelling of the new tests; the reviewer reads the spec
  - the code path that already worked
  - "as discussed" and how the bug was found
  - a review path or reading order; the author adds one if needed

Style:

- Prefer short and readable over grammatical.
  Fragments are fine.
- State facts as short bullets and sub-bullets.
- Separate blocks of information with blank lines.
- State what is, not what isn't.
  No double negatives.
- No "..., so ..." tails.
  If something needs explaining, give it its own sentence or bullet, and do that rarely.
- No em dashes.
- Use inline code at most once or twice per paragraph.
  Name a code element in bold or italics instead.
  To show code, use a fenced code block with an example.
- Tick only the checklist items that are true for this PR.
-->
