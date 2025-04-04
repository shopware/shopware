# Shopware RFC Process – GitHub Community Contributions

## Purpose
This document establishes a standardized process for submitting and reviewing Requests for Comments (RFCs) within the Shopware community. The goal is to have a transparent, inclusive, and efficient decision-making workflow for changes such as API additions, core reworks, or removals of public features to the Shopware codebase or policies.

## When to Submit an RFC

### Use the RFC process if your proposal:

- Introduces or removes public APIs (Store API, Admin Extension API, etc.).
- Changes architectural patterns or internal conventions.
- Involves multiple product units, teams, or repositories.
- Requires input from roles like UX, DevRel, QA, or Cloud.
- Affects developers, extension builders, or merchants in a non-trivial way.
- Is something that would need documentation, training, or a product update.
- Would likely generate a Shopware blog post upon release.
- Represents a long-term commitment or a migration path.

### You don’t need an RFC if the proposal is:

- Bug fixes or performance optimization
- Internal-only cleanup or refactor
- A configuration or label change that does not affect usage or expectations
- UI tweaks not requiring UX alignment

Remember that UserVoic is the place to suggest product features.
When in doubt, please open a discussion in the relevant GitHub issue or Slack channel.

## Process Overview

### 1. Prepare your idea

- If you're unsure whether your proposal needs an RFC, refer to the [When to Submit an RFC](##when-to-submit-an-rfc) section.
- Start a GitHub Discussion or comment on a relevant issue to collect early feedback to shape an idea before drafting a formal RFC.

### 2. Create your RFC

- In the [`shopware/shopware`](https://github.com/shopware/shopware) repository, copy the [RFC template](https://github.com/shopware/shopware/blob/trunk/rfcs/0000-template.md) to a new file inside the [`/rfcs`](https://github.com/shopware/shopware/tree/trunk/rfcs) folder.
- Name the file `0000-my-feature-name.md`. Keep the `0000` until the RFC is merged.
- Structure your proposal clearly using the template, including real-world motivation, technical details, and potential drawbacks.

### 3. Submit a Pull Request

- Open a pull request targeting the `trunk` branch.
- Include a brief summary in the PR description and link to any relevant GitHub Discussions or issues.
- Label the PR with `Type:RFC` (or your reviewer will) and select the relevant domain.

### 4. Assign a Review Facilitator

Once the RFC is submitted, a **Review Facilitator** will be assigned.

The Facilitator is responsible for:
- Making sure the proposal is reviewed by the right stakeholders (e.g., QA, UX, DevRel, Cloud, etc.).
- Guiding the discussion.
- Tracking open questions.
- Deciding when the RFC is ready to proceed to the next phase.

### 5. Community & Shopware Team Feedback

- The RFC PR remains open for review and iterative feedback.
- Reviewers may request clarifications, alternative approaches, or additional use cases.
- Major concerns should be resolved through constructive discussion.
- The Facilitator may invite reviewers from other teams if the proposal has cross-cutting impact.

### 6. Final Comment Period (FCP)

Once the Facilitator and relevant stakeholders agree that the proposal is stable and no major objections remain, the RFC enters a **10-day Final Comment Period (FCP)**.

- During FCP, contributors can raise final questions or blocking objections.
- If no significant issues are raised, the Facilitator will approve the merge after 15 days.
- If concerns emerge, the RFC returns to active discussion.

### 7. Acceptance & Merge

If approved, the Facilitator (or a Shopware team member) merges the RFC and:
- Renames the file from `0000-...` to an assigned number (e.g., `0023-my-feature.md`)
- Updates metadata (status, version, domain)

### 8. Post-Merge Follow-up

The Facilitator or another responsible team member may:
- Create follow-up tickets for implementation
- Notify impacted teams
- Add the RFC to internal or developer-facing documentation

Note: Merging an RFC doesn’t mean the feature is implemented. It means the idea is accepted as good to move forward into planning or development.

### 9. Implementation Phase

Once the RFC is merged, implementation can begin.

- The RFC author, or any interested contributor, may start development.
- All work should link back to the RFC pull request for traceability.
- Significant deviations from the agreed design should trigger a follow-up RFC or discussion.

Implementation may involve:
- Creating one or more GitHub issues or PRs.
- Involving QA, UX, DevRel, or other roles depending on impact.
- Adding changelogs, upgrade guides, or developer documentation where needed.

## Governance & Veto Handling
Shopware promotes a consensus-driven approach to RFCs. Disagreement is normal—but it should be handled transparently and constructively.

- During the Final Comment Period (FCP), silence is interpreted as agreement (“no response = no veto”).
- Objections should be supported with reasoning, such as risks, edge cases, or better alternatives.
- The Review Facilitator is responsible for managing disagreements and may:
    - Escalate concerns to core maintainers
    - Reopen the RFC after merge if new, critical objections emerge
    - Delay merge if stakeholder input is missing

For RFCs touching critical areas (e.g. Cloud rollout, Admin UI, API contracts, DAL behaviors), domain experts are consulted before approval.

## Tips for a Successful RFC

- Collaborate early and revise based on community feedback.
- Focus on design, goals, and impact—leave implementation details for follow-up.
- Anticipate how changes affect extensions, integrations, or B2B/B2C merchants.
- Keep the scope tight—large features can be split into multiple RFCs.
- Don’t worry if your RFC isn’t accepted right away—every proposal helps improve the platform.

## Inspired By
This RFC process was shaped by open source communities we admire:

[Rust RFC Process](https://github.com/rust-lang/rfcs)
[Vue RFC Process](https://github.com/vuejs/rfcs)
[Kubernetes Enhancements](https://github.com/kubernetes/enhancements)
[Python PEP Process](https://peps.python.org/pep-0001/)