---
title: Administration Pull Request Guidelines
date: 2026-05-13
area: administration
tags: [administration, pull-request, review, developer-experience]
---

## Context

Administration pull requests are often reviewed by people who do not have the same feature context as the author. Without clear review guidance, reviewers must reconstruct the affected area, the reason for the diff, and the intended behavior from code alone. This slows down reviews and increases the risk that important changes are missed while minor feedback receives too much attention.

## Decision

Administration pull requests must be prepared for reviewers who do not share the author's context:

* **Review your PR diff:** Authors review their own diff before requesting review and check whether the PR is understandable, sufficiently scoped, and small enough.
* **Explain the area:** PR descriptions explain the affected area when the change touches a niche or non-obvious feature. They should link existing documentation or briefly describe the relevant concept.
* **How to reproduce:** PR descriptions include concrete reproduction or verification steps. They state the behavior before and after the change and explain how a reviewer can confirm that the PR solves the problem.
* **Give context how to review:** PR descriptions explain non-obvious diffs. If a change is hard to understand from the diff alone, the explanation belongs in the PR description or as a GitHub comment on the relevant lines.
* **How should I review your PR?**: PR descriptions guide the review order when useful. For example: read a concept document first, then the test file, then the API implementation.
* **Only small PRs:** Administration PRs should stay below 500 changed lines. Larger changes should be split into stacked PRs with a clear review order.
* **Pareto Principle:** Reviewers focus on the small part of the change that carries most of the risk or impact, and avoid spending review attention on low-impact feedback that tooling or existing guidelines already cover.
* **Code Guidelines:** Authors and reviewers follow the existing Administration coding guidelines and required linting.

## Consequences

Administration PRs become easier to understand, verify, and review asynchronously. Authors invest more effort before requesting review, but reviewers need less time to reconstruct context. Large Administration changes need to be planned as smaller, stackable PRs instead of being reviewed as one broad diff.
