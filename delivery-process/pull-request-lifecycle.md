# Pull request lifecycle

Automation keeps track of open pull requests: who has to act next, when a team gets reminded, and when an inactive pull request gets warned and closed.

## Table of contents

- [What counts as activity](#what-counts-as-activity)
- [Waiting-on labels](#waiting-on-labels)
- [Slack reminders for external contributions](#slack-reminders-for-external-contributions)
- [Inactive pull requests](#inactive-pull-requests)
- [Labels](#labels)

## What counts as activity

All of the automation below measures activity the same way: a person has to move the pull request forward.
GitHub's "updated" date is not used, because label, milestone and bot changes move it too.

| Counts | Does not count |
|---|---|
| A commit on the head branch | Label, milestone, assignee and reviewer changes |
| A comment | Base branch changes |
| A submitted review or a reply in a review thread | CI runs |
| | Anything a bot posts |

## Waiting-on labels

Every open pull request in shopware/shopware carries one `waiting-on/` label, updated when the pull request changes and once a day at 06:00 UTC:

- `waiting-on/author`: the author has to act, for example after changes were requested, on an open question in an approved pull request, or after a rebase became necessary.
- `waiting-on/shopware`: we have to act, for example a first or second review, a re-review, or a merge.
- `waiting-on/nobody`: a draft the author is still working on, or a pull request labeled `lifecycle/DoNotClose`.

A draft that someone other than the author converted counts as `waiting-on/author`.
The full rule set lives in [`.github/bin/js/waiting-on.ts`](../.github/bin/js/waiting-on.ts).

## Slack reminders for external contributions

Teams can get Slack messages about pull requests labeled `external-contribution`.
A team opts in with an entry in the `WAITING_ON_SLACK_CHANNELS` repository variable, mapping its `domain/` label to a Slack channel.

- **New pull request:** one message once a pull request carries both `external-contribution` and the team's `domain/` label.
- **Reminder:** once it has been `waiting-on/shopware` for 14 days, then every 7 days for as long as that stays true.

A pull request that waits on its author triggers no reminder.

## Inactive pull requests

A daily job at 12:00 UTC retires inactive pull requests across all repositories of the shopware organization.
It only handles our own pull requests: the author and the first assignee both need a verified Shopware email address.
Community contributions and pull requests without an assignee are never warned or closed.

1. **Warning:** after 28 days without activity, the pull request gets the `lifecycle/AboutToClose` label and a comment.
   If it is not a draft yet, it is also converted to one.
2. **Closing:** with no activity in the 28 days after the label was added, it is closed and gets a comment.
   `lifecycle/AboutToClose` is replaced by `lifecycle/ClosedDueToNoActivity`.

Drafts are handled the same way, whoever created them.
For a draft, the 28 days start at its latest conversion to draft at the earliest.

The warning is withdrawn, and `lifecycle/AboutToClose` removed, as soon as one of these happens:

- someone is active on the pull request,
- it is marked as ready for review,
- it gets the `lifecycle/DoNotClose` label, which also excludes it from both steps for good, or
- someone removes `lifecycle/AboutToClose` by hand.

If the pull request stays inactive, it is warned again before it can be closed.

A closed pull request can be reopened at any time.
The job lives in [shopware/gh-project-automation](https://github.com/shopware/gh-project-automation) (`manageOldPullRequests`) and runs from [`manage-stale-prs.yml`](../.github/workflows/manage-stale-prs.yml).

## Labels

| Label | Set by | Meaning |
|---|---|---|
| `waiting-on/author`, `waiting-on/shopware`, `waiting-on/nobody` | Automation | Who has to act next, see [Waiting-on labels](#waiting-on-labels). |
| `lifecycle/DoNotClose` | People | Excludes the issue or pull request from automatic warning and closing. |
| `lifecycle/AboutToClose` | Automation | Warned; will be closed unless there is activity within 28 days. |
| `lifecycle/ClosedDueToNoActivity` | Automation | Closed after a warning and another 28 days without activity. |
