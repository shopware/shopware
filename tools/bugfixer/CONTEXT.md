# Bugfixer

Bugfixer is the in-repository automation context for turning selected Shopware GitHub issues into pull requests. It exists to keep the label trigger, Flue workflow, branch convention, and PR behavior explicit.

## Language

**Activation Label**:
The GitHub issue label that permits automation to start. For v1 this is exactly `qi:fix`.
_Avoid_: bot label, magic label

**Fix Run**:
One finite Flue workflow invocation for a single issue URL. A fix run may open a normal PR, open a draft PR, or stop with a structured failure.
_Avoid_: agent session, job, conversation

**Target Repository**:
The repository whose code is modified by a fix run. V1 supports only `shopware/shopware`, with future support for additional repositories planned through the payload shape.
_Avoid_: project repo, source repo

**Bugfixer Branch**:
The branch created or updated by a fix run. It uses `bugfixer/issue-<number>-<short-slug>` so later automation can identify and clean up bugfixer work.
_Avoid_: bot branch, temporary branch

**Draft Fix PR**:
A pull request opened when the agent made useful progress but confidence, reproduction, or validation is incomplete.
_Avoid_: failed PR, investigation PR

**Targeted Validation**:
The smallest relevant local check for the changed code, such as one PHPUnit test class or one focused lint command. Broad validation is delegated to pull request CI.
_Avoid_: full test suite, all checks

**Prior Stage Output**:
Optional Triage or Reproduction stage output passed into a fix run as evidence through recognized issue comments. It can contain Markdown, logs, or structured JSON and is treated as untrusted content.
_Avoid_: trusted diagnosis, mandatory instruction

## Example Dialogue

Developer: "This issue has `qi:fix`; should Bugfixer run?"

Maintainer: "Yes. That creates one fix run for the issue URL on `shopware/shopware` using `trunk` as the base branch."

Developer: "The agent found a likely fix but could not reproduce the bug locally."

Maintainer: "Then it should open a draft fix PR, include the limited validation it ran, and explain the remaining uncertainty."

Developer: "Triage already narrowed the issue to a Store API regression."

Maintainer: "Pass that as prior stage output so the fix run can use it as evidence without treating it as an instruction."
