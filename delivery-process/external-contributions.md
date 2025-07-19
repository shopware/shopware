# Working with External GitHub Contributions

This document outlines various approaches for working with pull requests (PRs) from external contributors on GitHub.

## Checking Out External Pull Requests

### Using IDE Integration

Most modern IDEs have GitHub integrations that allow you to:
- Browse all PRs
- Review PR files
- Check out PRs with a single click

#### Useful sources
- [Guide for PHPStorm](https://www.jetbrains.com/help/phpstorm/work-with-github-pull-requests.html)
- [Guide for VS Code](https://code.visualstudio.com/docs/sourcecontrol/github)

### Using GitHub CLI

Install the [GitHub CLI tool](https://cli.github.com) (`gh`) and use:

```bash
gh pr checkout <PR-ID>
```

### Using Git Commands

You can directly pull external PRs with the following Git commands:

```bash
git fetch origin pull/<PR-ID>/head:<BRANCH_NAME>
git switch <BRANCH_NAME>
```

Replace `<PR-ID>` with the PR number and `<BRANCH_NAME>` with your desired branch name.

This allows you to check out PRs and push against them directly without configuring remotes.

## Pushing Changes to External PRs

### Prerequisites

To push changes to a PR from a fork, the contributor must have checked "Allow edits from maintainers" when creating the PR.

To check if this option is enabled:
1. Go to the "Files changed" tab in the PR
2. Click the three dots menu next to a file
3. If "Edit file" is grayed out, the option is not checked

If the option is not enabled, ask the contributor to enable it.

### Using IDE Integration

Commit your changes in your IDE and push them directly to the PR branch. 
Most IDEs will automatically set the correct remote for you.
See the above-mentioned links for more information on how to do this.

### Checked out with GitHub CLI

After checking out the PR with `gh pr checkout <PR-ID>`:
1. Make your changes
2. Commit your changes: `git add . && git commit -m "Your message"`
3. Push using standard git commands: `git push`

The GitHub CLI should automatically set the correct remote, so a simple push should work.

### Adding a Remote for the Fork

If you need more control or are experiencing issues with the GitHub CLI:

1. Add the fork's repository as a new remote:
   ```bash
   git remote add fork-owner git@github.com:fork-owner/repository-name.git
   ```
   Replace `fork-owner` with the GitHub username of the fork owner and `repository-name` with the name of the repository.

2. Push your commits to this remote:
   ```bash
   git push fork-owner branch-name
   ```

## When You Can't Push to a PR

If the contributor has not enabled "Allow edits from maintainers", ask them politely to enable this option.
If they don't respond or refuse, you may need to create a new PR and close the old one (though this should be avoided if possible as it's less collaborative).
The new pull request will have two authors visible then, the original author and the committer.
