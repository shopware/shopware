# Merging the Base

Follow these steps in order.
Pushing, opening or closing a pull request and deleting a remote branch each need the user's request.

## Merge

1. Pick a merge branch name and check with `git ls-remote --heads origin <name>` that it is unused.
2. Create the merge branch from `origin/<head>`, never merge directly on the shared branch.
3. Run `git merge --no-ff origin/<base>` with the subject `Merge branch '<base>' into <head>` and no body.
4. In a conflicted hunk, keep the branch's side where the base's change reverses a decision documented on the branch, and list what was dropped.
5. Add the `required-for-green` adaptations to the merge commit.
   Keep `adoption` findings out of it.

## After the merge

- When a `dependency` item exists, update the local dependencies: `/composer.lock` is ignored by `.gitignore`, so the local install state goes stale.
- Run the generator of every `generated-artifact` item.
- Run the gates of every `gate` item.

## Pipeline

1. Open a pull request from the merge branch against the shared branch, for the pipeline only.
   Its body states that it must not be merged with the merge button when the repository squashes, because a squash drops the base as a parent.
   It lists the adaptations that are not mechanical.
2. Compare each failing job with the base branch's own recent runs before treating it as caused by the merge.

## Landing

1. Confirm the remote tip of the shared branch has not moved since the merge branch was created.
2. Push the merge commit to the shared branch as a fast-forward.
3. Close the pull request and delete the merge branch.
