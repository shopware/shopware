# Pull request lifecycle

Automation in this repository keeps track of open pull requests: who has to act next, and when a team gets reminded about an external contribution.

## Table of contents

- [Waiting-on labels](#waiting-on-labels)
- [Slack reminders for external contributions](#slack-reminders-for-external-contributions)
- [Inactive pull requests](#inactive-pull-requests)

## Waiting-on labels

Every open pull request carries one `waiting-on/` label, updated when the pull request changes and once a day at 06:00 UTC:

- `waiting-on/author`: the author has to act, for example after changes were requested, on an open question in an approved pull request, or after a rebase became necessary.
- `waiting-on/shopware`: we have to act, for example a first or second review, a re-review, or a merge.
- `waiting-on/nobody`: a draft the author is still working on, or a pull request labeled `lifecycle/DoNotClose`.

A draft that someone other than the author converted counts as `waiting-on/author`.
Only what a person does moves a pull request between these states;
label, milestone and bot changes do not.
The full rule set lives in [`.github/bin/js/waiting-on.ts`](../.github/bin/js/waiting-on.ts).

## Slack reminders for external contributions

Teams can get Slack messages about pull requests labeled `external-contribution`.
A team opts in with an entry in the `WAITING_ON_SLACK_CHANNELS` repository variable, mapping its `domain/` label to a Slack channel.

- **New pull request:** one message once a pull request carries both `external-contribution` and the team's `domain/` label.
- **Reminder:** once it has been `waiting-on/shopware` for 14 days, then every 7 days for as long as that stays true.

A pull request that waits on its author triggers no reminder.

## Inactive pull requests

Inactive pull requests are warned and then closed across the whole shopware organization, not only in this repository.
How that works, and how to keep a pull request open with `lifecycle/DoNotClose`, is described in [shopware/gh-project-automation](https://github.com/shopware/gh-project-automation/blob/main/docs/stale-pull-requests.md).
