#!/usr/bin/env node

import { execSync } from 'node:child_process';

interface PackageInfo {
  path: string;
  name: string;
  hasCustomAuditScript?: boolean;
}

interface AuditMatrixEntry {
  name: string;
  path: string;
  workingDirectory: string;
  hasCustomAuditScript: boolean;
  customAuditScriptPath: string | null;
  artifactName: string;
}

const FULL_AUDIT_TRIGGER_FILES = new Set([
  // Changes to shared audit behavior or package registry should re-run audits for every tracked package.
  '.github/bin/js/run-npm-audit.ts',
  '.github/bin/js/package-discovery.ts'
]);

/**
 * Expected package.json files that should be audited with their metadata
 */
export const EXPECTED_PACKAGE_JSON_FILES: readonly PackageInfo[] = [
  {
    path: './.github/bin/js/package.json',
    name: 'GitHub Bin JS'
  },
  {
    path: './src/Administration/Resources/app/administration/eslint-rules/core-rules/package.json',
    name: 'ESLint Core Rules'
  },
  {
    path: './src/Administration/Resources/app/administration/eslint-rules/deprecation-rules/package.json',
    name: 'ESLint Deprecation Rules'
  },
  {
    path: './src/Administration/Resources/app/administration/eslint-rules/plugin-rules/package.json',
    name: 'ESLint Plugin Rules'
  },
  {
    path: './src/Administration/Resources/app/administration/eslint-rules/test-rules/package.json',
    name: 'ESLint Test Rules'
  },
  {
    path: './src/Administration/Resources/app/administration/package.json',
    name: 'Administration Main',
    hasCustomAuditScript: true
  },
  {
    path: './src/Administration/Resources/app/administration/twigVuePlugin/package.json',
    name: 'Administration Twig Vue Plugin'
  },
  {
    path: './src/Storefront/Resources/app/administration/package.json',
    name: 'Storefront Administration'
  },
  {
    path: './src/Storefront/Resources/app/storefront/package.json',
    name: 'Storefront Main',
    hasCustomAuditScript: true
  },
  {
    path: './tests/acceptance/package.json',
    name: 'Tests Acceptance',
    hasCustomAuditScript: true
  }
] as const;

/**
 * Get just the file paths for backward compatibility
 */
export function getPackageJsonPaths(): readonly string[] {
  return EXPECTED_PACKAGE_JSON_FILES.map(pkg => pkg.path);
}

/**
 * Get working directory from package.json path
 */
export function getWorkingDirectoryFromPath(packageJsonPath: string): string {
  // Remove the '/package.json' suffix to get the directory
  return packageJsonPath.replace('/package.json', '');
}

export function getPackageLockPath(packageJsonPath: string): string {
  return packageJsonPath.replace('/package.json', '/package-lock.json');
}

export function getCustomAuditScriptPath(packageJsonPath: string): string {
  return `${getWorkingDirectoryFromPath(packageJsonPath)}/scripts/runNpmAudit.ts`;
}

function normalizePath(path: string): string {
  return path.replace(/^[.]\//, '');
}

function getArtifactName(packageJsonPath: string): string {
  return `npm-audit-${normalizePath(getWorkingDirectoryFromPath(packageJsonPath)).replace(/[^a-zA-Z0-9]+/g, '-')}`;
}

function toAuditMatrixEntry(pkg: PackageInfo): AuditMatrixEntry {
  return {
    name: pkg.name,
    path: pkg.path,
    workingDirectory: getWorkingDirectoryFromPath(pkg.path),
    hasCustomAuditScript: pkg.hasCustomAuditScript || false,
    customAuditScriptPath: pkg.hasCustomAuditScript ? getCustomAuditScriptPath(pkg.path) : null,
    artifactName: getArtifactName(pkg.path)
  };
}

/**
 * Find all package.json files in the repository
 */
export function findAllPackageJsonFiles(): string[] {
  try {
    const output = execSync(
      'find . -name "package.json" -not -path "*/node_modules/*" -not -path "*/vendor/*" -not -path "*/.tmp/*" | sort',
      { encoding: 'utf8', cwd: process.cwd() }
    );
    return output.trim().split('\n').filter(Boolean);
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    throw new Error(`Failed to find package.json files: ${errorMessage}`);
  }
}

/**
 * Validate that all found package.json files are in the expected list
 */
export function validatePackageCompleteness(): {
  success: boolean;
  unexpected: string[];
  missing: string[];
} {
  const foundPackages = findAllPackageJsonFiles();
  const expectedPackages = getPackageJsonPaths();

  // Check for unexpected files
  const unexpected = foundPackages.filter(found => !expectedPackages.includes(found));

  // Check for missing files
  const missing = expectedPackages.filter(expected => !foundPackages.includes(expected));

  return {
    success: unexpected.length === 0,
    unexpected,
    missing
  };
}

/**
 * Generate GitHub Actions matrix for audit jobs
 */
export function generateAuditMatrix(changedFiles: string[] = []): {
  include: AuditMatrixEntry[];
} {
  const normalizedChangedFiles = changedFiles
    .map(file => normalizePath(file.trim()))
    .filter(Boolean);

  if (
    normalizedChangedFiles.length === 0 ||
    normalizedChangedFiles.some(file => FULL_AUDIT_TRIGGER_FILES.has(file))
  ) {
    return {
      include: EXPECTED_PACKAGE_JSON_FILES.map(toAuditMatrixEntry)
    };
  }

  const changedFileSet = new Set(normalizedChangedFiles);
  const filteredPackages = EXPECTED_PACKAGE_JSON_FILES.filter((pkg) => {
    const packageJsonPath = normalizePath(pkg.path);
    const packageLockPath = normalizePath(getPackageLockPath(pkg.path));

    if (changedFileSet.has(packageJsonPath) || changedFileSet.has(packageLockPath)) {
      return true;
    }

    if (pkg.hasCustomAuditScript) {
      return changedFileSet.has(normalizePath(getCustomAuditScriptPath(pkg.path)));
    }

    return false;
  });

  return {
    include: filteredPackages.map(toAuditMatrixEntry)
  };
}

function readChangedFilesFromFile(filePath: string): string[] {
  try {
    return execSync(`cat "${filePath}"`, { encoding: 'utf8', cwd: process.cwd() })
      .split('\n')
      .map(line => line.trim())
      .filter(Boolean);
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    throw new Error(`Failed to read changed files from ${filePath}: ${errorMessage}`);
  }
}

/**
 * Main function when script is run directly
 */
function main(): void {
  const args = process.argv.slice(2);
  const command = args[0];

  switch (command) {
    case 'list':
      console.log(getPackageJsonPaths().join('\n'));
      break;

    case 'find':
      try {
        const found = findAllPackageJsonFiles();
        console.log(found.join('\n'));
      } catch (error) {
        console.error('Error:', error instanceof Error ? error.message : String(error));
        process.exit(1);
      }
      break;

    case 'matrix':
      try {
        const changedFiles =
          args[1] === '--changed-files-file' && args[2]
            ? readChangedFilesFromFile(args[2])
            : [];
        const matrix = generateAuditMatrix(changedFiles);
        console.log(JSON.stringify(matrix));
      } catch (error) {
        console.error('Error:', error instanceof Error ? error.message : String(error));
        process.exit(1);
      }
      break;

    case 'validate':
      try {
        const validation = validatePackageCompleteness();

        console.log('🔍 Validating package.json completeness...\n');

        if (validation.unexpected.length > 0) {
          console.log('❌ ERROR: Found unexpected package.json files that are not included in the audit workflow:');
          validation.unexpected.forEach(file => console.log(`   ${file}`));
          console.log('\nPlease add these files to EXPECTED_PACKAGE_JSON_FILES in .github/bin/js/package-discovery.ts');
        }

        if (validation.missing.length > 0) {
          console.log('⚠️  WARNING: Some expected package.json files are missing:');
          validation.missing.forEach(file => console.log(`   ${file}`));
        }

        if (validation.success && validation.missing.length === 0) {
          console.log('✅ All found package.json files are included in the audit workflow');
          process.exit(0);
        } else {
          process.exit(1);
        }
      } catch (error) {
        console.error('Error:', error instanceof Error ? error.message : String(error));
        process.exit(1);
      }
      break;

    default:
      console.log('Usage: npx esno package-discovery.ts <command>');
      console.log('Commands:');
      console.log('  list     - List expected package.json files');
      console.log('  find     - Find all package.json files in repository');
      console.log('  matrix [--changed-files-file <path>] - Generate GitHub Actions matrix for audit jobs');
      console.log('  validate - Validate package completeness');
      process.exit(1);
  }
}

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
