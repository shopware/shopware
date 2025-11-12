---
title: Pin All NPM Dependencies to Exact Versions
date: 2025-10-23
area: infrastructure
tags: [repository, workflow, npm, dependencies, ci, security]
---

## Context

NPM dependencies with range specifiers (e.g., `^1.2.3` or `~1.2.3`) allow automatic updates to newer compatible versions during installation. While convenient for library development, this creates several risks for application development:

1. **Non-Deterministic Builds**: Different developers or CI runs may install different versions, leading to "works on my machine" issues
2. **Security Risk Window**: Malicious packages can be introduced through automatic version updates without explicit review
3. **Breaking Changes**: Even minor/patch updates can introduce bugs or breaking changes despite semantic versioning
4. **Difficult Debugging**: Inconsistent dependency versions across environments make issues harder to reproduce and diagnose

## Decision

All NPM dependencies in Shopware 6 must be pinned to exact versions without range specifiers:
- ❌ `"package": "^1.2.3"` or `"package": "~1.2.3"`
- ✅ `"package": "1.2.3"`

This applies to both `dependencies` and `devDependencies` in all `package.json` files throughout the repository.

Automated CI checks have been implemented to:
- Discover all `package.json` files in the repository
- Validate that no unpinned dependencies exist
- Block merges if unpinned dependencies are found

These checks run via the `npm-audit-check.yml` workflow on every pull request and push to trunk.

## Consequences

### Positive

- **Reproducible Builds**: Identical dependency versions across all environments (local, CI, production)
- **Explicit Updates**: Dependency updates require intentional changes and code review
- **Enhanced Security**: Prevents automatic installation of compromised package versions
- **Easier Debugging**: Consistent versions make issues reproducible and easier to diagnose
- **Automated Enforcement**: CI pipeline ensures compliance without manual review

### Negative

- **Manual Dependency Updates**: Developers must explicitly update dependencies using `npm update` or `npm install package@version`
- **Increased Maintenance**: Regular dependency updates require more conscious effort
- **More Frequent PRs**: Security patches and updates must be applied through explicit pull requests

### For Developers

When adding or updating dependencies:
1. Always specify exact versions in `package.json`
2. Run `npm install` to update `package-lock.json`
3. The CI pipeline will reject PRs with unpinned dependencies
4. Use tools like `npm outdated` to check for available updates


