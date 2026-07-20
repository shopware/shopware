# NPM Audit & Dependency Management Scripts

TypeScript utilities for managing and validating package.json files across the Shopware repository.

## Scripts

### `package-discovery.ts`
Central registry of all package.json files with utilities for validation and matrix generation.

**Commands:**
- `npx esno package-discovery.ts list` - List all tracked package.json files
- `npx esno package-discovery.ts find` - Find all package.json files in repository
- `npx esno package-discovery.ts validate` - Validate all found files are tracked
- `npx esno package-discovery.ts matrix` - Generate the full GitHub Actions matrix
- `npx esno package-discovery.ts matrix --changed-files-file <path>` - Generate a filtered matrix for tracked packages touched by changed manifests, lockfiles, or audit scripts

### `check-pinned-dependencies.ts`
Validates that all dependencies use exact versions (no `^` or `~` prefixes).

**Usage:**
```bash
npx esno check-pinned-dependencies.ts
```

### `run-npm-audit.ts`
Shared audit logic used by per-project `scripts/runNpmAudit.ts` wrappers. Exports `runNpmAudit(options)` which runs `npm audit`, filters advisories matching the given GHSA/CVE identifiers, and suggests using `overrides` or adding to the ignore list when vulnerabilities are found.

The script also supports direct execution for projects without package-local wrappers and writes a normalized JSON summary when `NPM_AUDIT_RESULT_FILE` is set. CI uses that result file to aggregate scheduled audit failures into a single GitHub issue update.

Each project with known false-positives or unfixable advisories has a thin `scripts/runNpmAudit.ts` wrapper:
```typescript
import { runNpmAudit } from '../../../../../../.github/bin/js/run-npm-audit.ts';

runNpmAudit({
    ignoredGHSAs: [
        'https://github.com/advisories/GHSA-2g4f-4pwh-qvx6', // ajv ReDoS, moderate severity, devDep only
    ],
    ignoredCVEs: [
        'CVE-2025-58754', // axios DoS, false positive
    ],
});
```

GHSAs are specified as full `https://github.com/advisories/GHSA-xxxx-xxxx-xxxx` URLs so developers can click through directly. The GHSA identifier is extracted from the URL automatically.

When vulnerabilities are detected, the output includes GHSA and CVE identifiers for each advisory, and the suggestions section provides copy-pasteable GHSA URL entries for the ignore list.

## Adding New Package.json

When adding a new package.json file to the repository:

1. Add entry to `EXPECTED_PACKAGE_JSON_FILES` in `package-discovery.ts`:
   ```typescript
   {
       path: './path/to/your/package.json',
       name: 'Your Package Name',
       hasCustomAuditScript: true  // only if using scripts/runNpmAudit.ts
   }
   ```

2. If the package has known advisories to ignore, create `scripts/runNpmAudit.ts` in that package's directory
   that imports `runNpmAudit` from `.github/bin/js/run-npm-audit.ts` and passes the GHSA/CVE identifiers.

## CI Checks

The workflow automatically:

- **Package Completeness**: Ensures all package.json files are tracked
- **Dependency Pinning**: Validates all dependencies use exact versions
- **Security Audits**: Runs changed-package audits for PRs and pushes to `trunk`
- **Full Scheduled Audits**: Runs all package audits for scheduled and manual executions
- **Issue Reporting**: Creates or updates one GitHub issue when a scheduled audit fails
- **Dynamic Scaling**: New packages are automatically included once added to the registry

No manual workflow updates needed - just update the package list!
