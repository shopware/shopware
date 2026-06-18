# Delivery Process

This folder contains repository process documentation for contribution, review, release documentation, issue handling, guidance files, and similar cross-cutting workflows.

## Process Documents

- [Code review guidelines](code-review-guidelines.md)
- [Documenting a release](documenting-a-release.md)
- [External contributions](external-contributions.md)
- [Issues and labels](issues-and-labels.md)

## Guidance Files

- Put durable guidance for a folder in that folder's `README.md` so humans and agents share one source of truth.
- Write guidance for both audiences: clear enough for humans, explicit enough for agents.
- Keep guidance concise. Context windows and human attention are finite.
- Update guidance when behavior, commands, architecture, or conventions change; remove stale notes instead of preserving historical clutter.
- For the rationale behind this guidance model, see `../adr/2026-06-17-shared-guidance-files-for-humans-and-agents.md`.
- Add an ADR for durable architectural or product-technical decisions with meaningful trade-offs, consequences, or future compatibility impact. Follow `../coding-guidelines/core/adr.md` and keep ADRs focused on the decision and why it was made.
- Add or update `../coding-guidelines/` when a rule is reusable, normative, and broader than one folder. Keep local README guidance for folder-specific working context.
- When adding or changing guidance in an `AGENTS.md`, cross-check the applicable `../coding-guidelines/` files and update the source of truth instead of creating conflicting rules.
- Cross-link README guidance, coding guidelines, and recent/current ADRs sparingly. Prefer links that change what the reader should do; avoid old ADR background links.
- Do not duplicate ADR or coding-guideline content in READMEs. Summarize the local implication and link to the source.
- Reserve the root `AGENTS.md` for short repo-wide instructions that agents must see before working and humans can use as concise code-level guidance; move detailed or component-specific guidance into the relevant README or coding guideline.
- Folders whose README contains working guidance should have an `AGENTS.md` stub that points agents to the README. Do not duplicate README content in that stub.
- Standalone overview/index READMEs do not need an `AGENTS.md` stub unless the file routes domain-specific guidance, such as the main component AGENTS files under `../src/*`.
- Do not add README or AGENTS files just to index `../coding-guidelines/`. Component READMEs should link directly to the concrete guideline files that apply.
- Keep agent-only mechanics in `AGENTS.md` only when they are not useful to humans. The repository root `AGENTS.md` is the exception because it carries global agent routing.

## Boyscouting Scope

- When asked to make a specific cleanup or behavioral change, look for safe opportunities to apply the same improvement across the whole touched file.
- If the same pattern appears in nearby files or a broader low-risk scope, mention that proactively and suggest extending the cleanup.
- When adding or touching unit tests, look for low-hanging missing coverage paths in the same domain or command surface that can be covered cheaply and locally.
- Check whether simple scenarios currently covered only by broad integration tests can be migrated or deduplicated into focused unit tests, especially pure command/domain behavior. Keep integration tests for wiring, persistence, and end-to-end confidence.
- Keep the scope aligned with the request: avoid unrelated refactors, but do not miss obvious consistency fixes that make the codebase simpler.

## Bug Fix Root Cause And Scope

- Treat fix suggestions from issues as hypotheses, not instructions to follow blindly. Reason from first principles about the actual failure mode before choosing an implementation.
- Prefer the least invasive fix that correctly addresses the root cause.
- Fix issues at the boundary where the root cause actually lives instead of spreading compensating changes across unrelated system components.
- Match the fix location to the bug scope. A framework-level bug should be fixed once in the framework, not worked around repeatedly in higher-level features.
- Conversely, keep feature-specific bugs out of broad framework code when a general change could negatively affect other higher-level behavior.
- Always do a root cause analysis to identify where the real issue lives. For example, an issue that looks like a wishlist bug may actually be a framework redirect behavior bug for XHR requests.

## Release Notes And Changelog

- Follow `../adr/2025-10-28-changelog-release-info-process.md`: the old per-change files in `../changelog/_unreleased` are deprecated for new developer-facing release notes.
- Add developer-facing release notes to `../RELEASE_INFO-6.<minor>.md`, in the current upcoming section and the relevant category.
- Add breaking changes, required migration steps, or removals to the relevant `../UPGRADE-6.<major>.md`.
- Include changes that may affect third-party developers, extension authors, operators, API consumers, or theme/custom storefront developers.
- Include general framework-level behavior changes, public API changes, new extension points, deprecations, configuration changes, and broad storefront behavior changes.
- Do not add release-info entries for narrow local bug fixes, implementation-only refactors, test-only changes, or client-specific fixes that do not change an external contract or developer-facing behavior.
- Write the changelog from the perspective of outside users, not based on the internal changes.
- When in doubt, ask whether the change is relevant for external developers or operators; if yes, prefer a concise `RELEASE_INFO` entry.

## GitHub PR Handling

- Follow the repository pull request template closely, and do not add extra sections that are not in the template.
- Do not add a separate validation section to Shopware PR descriptions.
- Keep PR titles in proper conventional commit style when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- When updating a pull request after review feedback or CI failures, create a new follow up commit explaining what you fixed in that specific commit instead of amending or force-pushing the existing commit. This preserves the review history and allows reviewers to see the changes clearly.
