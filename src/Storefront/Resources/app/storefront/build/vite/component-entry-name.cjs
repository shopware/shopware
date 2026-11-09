'use strict';

/* global console, process */

// Keep this helper in CommonJS so the directly executed build-components.js
// and the TypeScript build configs can share the same implementation.

/**
 * Normalizes a component path to its entry name, removing an `index` file
 * segment while optionally keeping the source extension for style entries.
 *
 *   `Sw/Foo/index.js`       → `Sw/Foo`
 *   `Sw/Foo/index.scss`     → `Sw/Foo.scss` (when preserveExtension is true)
 */
exports.normalizeComponentEntryName = (filePath, preserveExtension = false) => {
    const extensionMatch = filePath.match(/\.(js|ts|scss|css)$/);
    const extension = extensionMatch ? extensionMatch[0] : '';
    const name = extension ? filePath.slice(0, -extension.length) : filePath;
    const normalizedName = name.replace(/\/index$/, '');

    return preserveExtension ? `${normalizedName}${extension}` : normalizedName;
};

/**
 * Logs duplicate normalized entry names without stopping the build.
 * The final entry keeps the same last-file-wins behavior as Object.fromEntries.
 */
exports.warnForDuplicateEntryNames = (files, getEntryName, context, entryType) => {
    const filesByEntryName = new Map();
    const warningLabel = process.stderr.isTTY && !process.env.NO_COLOR
        ? '\u001B[33m[WARNING]\u001B[39m'
        : '[WARNING]';

    for (const file of files) {
        const entryName = getEntryName(file);
        const previousFile = filesByEntryName.get(entryName);
        if (previousFile) {
            console.warn(
                `${warningLabel} ${context}: ${entryType} entry "${entryName}" is produced by both `
                + `"${previousFile}" and "${file}". The latter will replace the former.`,
            );
        }
        filesByEntryName.set(entryName, file);
    }
};
