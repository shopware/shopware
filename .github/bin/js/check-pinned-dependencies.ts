#!/usr/bin/env node

import { readFileSync, existsSync } from 'node:fs';
import { getPackageJsonPaths } from './package-discovery.js';

interface PackageJsonContent {
    dependencies?: Record<string, string>;
    devDependencies?: Record<string, string>;
    peerDependencies?: Record<string, string>;
}

interface CheckResult {
    file: string;
    errors: string[];
    warnings: string[];
    hasUnpinnedDeps: boolean;
}

const semverPattern =
    /^(\d+)\.(\d+)\.(\d+)(-[\w-]+(\.[\w-]+)*)?(\+[\w-]+(\.[\w-]+)*)?$/;

const containsCommitishPattern = /[a-f0-9]{5,40}/;

const containsSemverPattern =
    /(\d+)\.(\d+)\.(\d+)(-[\w-]+(\.[\w-]+)*)?(\+[\w-]+(\.[\w-]+)*)?/;

function isUrl(version: string): boolean {
    return version.includes("/");
}

interface VersionIsPinnedOptions {
    ignoreWorkspaces?: boolean;
    ignoreCatalog?: boolean;
}

// source: https://github.com/raulfdm/pin-dependencies-checker/blob/main/lib/versionIsPinned.ts
export function versionIsPinned(
    version: string,
    options?: VersionIsPinnedOptions,
): boolean {
    if (version === "" || version === "latest" || version === "*") {
        return false;
    }

    const firstCharacter = version[0] ?? "";

    if (["^", ">", "<", "~"].includes(firstCharacter)) {
        return false;
    }

    if (version.startsWith("file:")) {
        return true;
    }

    if (version.startsWith("workspace:")) {
        if (options?.ignoreWorkspaces) {
            return true;
        }
        return semverPattern.test(version.substring("workspace:".length));
    }

    if (version.startsWith("catalog:")) {
        if (options?.ignoreCatalog) {
            return true;
        }

        // Catalogs don't follow semver, so we don't need to check for it
        return false;
    }

    // Support package aliases: https://pnpm.io/aliases (Also works in npm and yarn)
    if (version.startsWith("npm:")) {
        const aliasedVersion = version.split("@").at(-1);
        if (!aliasedVersion) return false;
        return semverPattern.test(aliasedVersion);
    }

    if (isUrl(version)) {
        return (
            containsSemverPattern.test(version) ||
            containsCommitishPattern.test(version)
        );
    }

    return semverPattern.test(version) || isUrl(version);
}

/**
 * Check dependencies in a package.json file for pinning
 */
export function checkPackageJsonPinning(packageJsonPath: string): CheckResult {
    const result: CheckResult = {
        file: packageJsonPath,
        errors: [],
        warnings: [],
        hasUnpinnedDeps: false
    };

    try {
        const packageContent: PackageJsonContent = JSON.parse(readFileSync(packageJsonPath, 'utf8'));

        // Check dependencies
        if (packageContent.dependencies) {
            for (const [depName, version] of Object.entries(packageContent.dependencies)) {
                if (!versionIsPinned(version)) {
                    result.errors.push(`Unpinned dependency: ${depName}@${version}`);
                    result.hasUnpinnedDeps = true;
                }
            }
        }

        // Check devDependencies
        if (packageContent.devDependencies) {
            for (const [depName, version] of Object.entries(packageContent.devDependencies)) {
                if (!versionIsPinned(version)) {
                    result.errors.push(`Unpinned devDependency: ${depName}@${version}`);
                    result.hasUnpinnedDeps = true;
                }
            }
        }

        // Check peerDependencies (optional - usually these can be ranges)
        if (packageContent.peerDependencies) {
            for (const [depName, version] of Object.entries(packageContent.peerDependencies)) {
                if (!versionIsPinned(version)) {
                    result.warnings.push(`Unpinned peerDependency: ${depName}@${version} (peerDependencies can typically use ranges)`);
                }
            }
        }

    } catch (error) {
        const errorMessage = error instanceof Error ? error.message : String(error);
        result.errors.push(`Failed to read or parse ${packageJsonPath}: ${errorMessage}`);
    }

    return result;
}

/**
 * Main function to check all package.json files
 */
function main(): void {
    const packageJsonFiles = getPackageJsonPaths();

    let hasErrors = false;
    const results: CheckResult[] = [];

    console.log('🔍 Checking dependency pinning in package.json files...\n');

    for (const packageJsonPath of packageJsonFiles) {
        if (!existsSync(packageJsonPath)) {
            console.log(`⚠️  Warning: ${packageJsonPath} not found, skipping...`);
            continue;
        }

        const result = checkPackageJsonPinning(packageJsonPath);
        results.push(result);

        if (result.errors.length > 0) {
            hasErrors = true;
            console.log(`❌ ${result.file}:`);
            result.errors.forEach(error => console.log(`   ${error}`));
            console.log();
        } else {
            console.log(`✅ ${result.file}: All dependencies are pinned`);
        }

        if (result.warnings.length > 0) {
            console.log(`⚠️  ${result.file} warnings:`);
            result.warnings.forEach(warning => console.log(`   ${warning}`));
            console.log();
        }
    }

    // Summary
    const totalFiles = results.length;
    const filesWithErrors = results.filter(r => r.hasUnpinnedDeps).length;
    const filesWithWarnings = results.filter(r => r.warnings.length > 0).length;

    console.log('📊 Summary:');
    console.log(`   Total files checked: ${totalFiles}`);
    console.log(`   Files with unpinned dependencies: ${filesWithErrors}`);
    console.log(`   Files with warnings: ${filesWithWarnings}`);

    if (hasErrors) {
        console.log('\n❌ FAILED: Found unpinned dependencies. Please pin all dependencies to exact versions.');
        console.log('   Example: Change "^1.2.3" to "1.2.3"');
        process.exit(1);
    } else {
        console.log('\n✅ SUCCESS: All dependencies are properly pinned!');
        process.exit(0);
    }
}

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    main();
}
