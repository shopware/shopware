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

/**
 * Check if a dependency version is pinned (no ^ or ~ prefix)
 */
function isPinned(version: string): boolean {
    // Check if version starts with ^ or ~ (unpinned)
    return !version.startsWith('^') && !version.startsWith('~');
}

/**
 * Check dependencies in a package.json file for pinning
 */
function checkPackageJsonPinning(packageJsonPath: string): CheckResult {
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
                if (!isPinned(version)) {
                    result.errors.push(`Unpinned dependency: ${depName}@${version}`);
                    result.hasUnpinnedDeps = true;
                }
            }
        }

        // Check devDependencies
        if (packageContent.devDependencies) {
            for (const [depName, version] of Object.entries(packageContent.devDependencies)) {
                if (!isPinned(version)) {
                    result.errors.push(`Unpinned devDependency: ${depName}@${version}`);
                    result.hasUnpinnedDeps = true;
                }
            }
        }

        // Check peerDependencies (optional - usually these can be ranges)
        if (packageContent.peerDependencies) {
            for (const [depName, version] of Object.entries(packageContent.peerDependencies)) {
                if (!isPinned(version)) {
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

export { checkPackageJsonPinning, isPinned };
