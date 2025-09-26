# NPM Audit & Dependency Management Scripts

TypeScript utilities for managing and validating package.json files across the Shopware repository.

## Scripts

### `package-discovery.ts`
Central registry of all package.json files with utilities for validation and matrix generation.

**Commands:**
- `npx esno package-discovery.ts list` - List all tracked package.json files
- `npx esno package-discovery.ts find` - Find all package.json files in repository
- `npx esno package-discovery.ts validate` - Validate all found files are tracked
- `npx esno package-discovery.ts matrix` - Generate GitHub Actions matrix

### `check-pinned-dependencies.ts`
Validates that all dependencies use exact versions (no `^` or `~` prefixes).

**Usage:**
```bash
npx esno check-pinned-dependencies.ts
```

## Adding New Package.json

When adding a new package.json file to the repository:

1. Add entry to `EXPECTED_PACKAGE_JSON_FILES` in `package-discovery.ts`:
   ```typescript
   {
       path: './path/to/your/package.json',
       name: 'Your Package Name',
       hasCustomAuditScript: true  // only if needed
   }
   ```

2. Set `hasCustomAuditScript: true` only if the package uses a custom audit script instead of `npm audit`

## CI Checks

The workflow automatically:

- ✅ **Package Completeness**: Ensures all package.json files are tracked
- ✅ **Dependency Pinning**: Validates all dependencies use exact versions
- ✅ **Security Audits**: Runs npm audit on all packages in parallel
- ✅ **Dynamic Scaling**: New packages are automatically included in all checks

No manual workflow updates needed - just update the package list!
