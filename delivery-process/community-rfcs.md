# Shopware RFC Process – GitHub Community Contributions

## Purpose
This document establishes a standardized process for submitting and reviewing Requests for Comments (RFCs) within the Shopware community. The goal is to have a transparent, inclusive, and efficient decision-making workflow for substantial changes to the Shopware codebase or policies.

## When to Submit an RFC
The RFC process applies to all significant modifications, including but not limited to:

- Introduction of new features or APIs
- Deprecation or removal of existing features
- Changes that affect the public interface or behavior
- Modifications to the development process or policies

Minor changes, bug fixes, and documentation updates that do not have broad implications can proceed through the standard pull request workflow without an RFC.

## Process Overview
### Prepare your idea

- Discuss informally in GitHub issues, Slack, or community channels.
- An RFC is initiated by creating a new issue in the shopware/rfcs repository using the RFC template provided.

### Create your RFC

- Fork the shopware/rfcs repo.
- Copy 0000-template.md → rfcs/0000-my-feature-name.md.
- Fill in your proposal using clear, thoughtful reasoning.

### Submit a Pull Request

- Open a PR to the shopware/rfcs repo with the new file.
- Link to any pre-discussion or related issue.
- Don’t assign an RFC number yet (the PR number will be used).

### Community & Core Team Feedback

- The PR remains open for feedback and iteration.
- Proposal is labeled with the relevant domain/* and assigned a Review Facilitator (usually an internal TDM/PM/Engineer).
- Substantial changes may require UX/DevRel/Product/Cloud input.

### Final Comment Period (FCP)

- After consensus-building, the facilitator proposes an FCP. We suggest a 10 days time frame.
- This period is publicly announced (e.g. via Slack, GitHub Discussions).
- Silence during this phase does not mean implicit agreement. Please make sure the necessary feedback is gathered.

### Decision & Merge

- If accepted: the RFC is merged and marked active.
- If rejected: closed with a rationale comment.
- If unresolved: marked postponed, pending future iteration.

### Implementation Phase

- The RFC author or any contributor may start implementing.
- Link the implementation PR back to the RFC.
- Significant deviations must be raised via follow-up PRs.

## Governance & Veto Handling
Shopware promotes a consensus-seeking model: disagreement is expected, but collaboration is key.

1. A “no response = no veto” policy applies during the FCP. Concerns must be raised during the open discussion or FCP window.
2. Objections must be reasoned, not based on gut feeling or personal preference.
3. The Review Facilitator may resolve edge cases, escalate to core maintainers, or reopen if strong community objections arise post-FCP.
4. Major areas (Cloud, Admin, API, DAL, etc.) may have domain experts who are consulted before merge.

## Tips for Successful RFCs
- Collaborate early and respond respectfully.
- Keep implementation out of scope—focus on design decisions.
- Highlight impacts on extensions, SaaS setups, or B2B/B2C merchants.
- Don't worry if the RFC is declined—every proposal helps evolve Shopware.


## Inspired by
This RFC process has been inspired by:
- [Rust RFC Process](https://github.com/rust-lang/rfcs)
- [Vue RFC Process](https://github.com/vuejs/rfcs)