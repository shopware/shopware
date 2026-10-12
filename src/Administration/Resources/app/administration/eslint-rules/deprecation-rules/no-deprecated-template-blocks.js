/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

function buildMessage(migration, usage) {
    const replacement = usage.to ? ` Use "${usage.to}" instead.` : ' Remove or move this customization.';

    return appendRegistryContext([`The Administration Twig block "${usage.from}" is deprecated.${replacement}`], migration);
}

function collectBlockMatches(sourceText) {
    const matches = [];
    const twigBlockPattern = /\{%\s*block\s+([A-Za-z0-9_]+)\s*%}/g;
    const swBlockPattern = /<sw-block\b[^>]*>/g;
    const swBlockAttributePattern = /\s(?:name|extends)\s*=\s*(["'])([^"']+)\1/g;

    let match = twigBlockPattern.exec(sourceText);
    while (match) {
        matches.push({
            name: match[1],
            start: match.index + match[0].indexOf(match[1]),
            end: match.index + match[0].indexOf(match[1]) + match[1].length,
        });

        match = twigBlockPattern.exec(sourceText);
    }

    match = swBlockPattern.exec(sourceText);
    while (match) {
        let attributeMatch = swBlockAttributePattern.exec(match[0]);

        while (attributeMatch) {
            const blockName = attributeMatch[2];
            const blockNameStart = match.index + attributeMatch.index + attributeMatch[0].lastIndexOf(blockName);

            matches.push({
                name: blockName,
                start: blockNameStart,
                end: blockNameStart + blockName.length,
            });

            attributeMatch = swBlockAttributePattern.exec(match[0]);
        }

        match = swBlockPattern.exec(sourceText);
    }

    return matches;
}

/**
 * A file that defines the replacement block itself keeps the old name as a BC wrapper, not as an extension usage.
 *
 * @example
 * isBackwardCompatibilityWrapper({ usage: { from: 'old_block', to: 'new_block' } }, new Set(['old_block', 'new_block'])); // true
 */
function isBackwardCompatibilityWrapper(match, definedBlockNames) {
    return Boolean(match.usage.to) && definedBlockNames.has(match.usage.to);
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration Twig blocks',
            recommended: true,
        },
        fixable: 'code',
        schema: [
            {
                enum: [
                    'enableFix',
                    'disableFix',
                ],
            },
        ],
    },

    create(context) {
        const registry = loadRegistry();
        const usages = filterMigrations(registry.templateBlockMigrations).flatMap((migration) => {
            return migration.usage
                .filter((usage) => usage.kind !== 'replace-template-block')
                .map((usage) => {
                    return {
                        migration,
                        usage,
                    };
                });
        });

        return {
            Program() {
                const sourceCode = context.sourceCode;
                const sourceText = sourceCode.getText();

                const blockMatches = collectBlockMatches(sourceText);
                const definedBlockNames = new Set(blockMatches.map((blockMatch) => blockMatch.name));

                blockMatches.forEach((blockMatch) => {
                    const match = usages.find(({ usage }) => usage.from === blockMatch.name);

                    if (!match || isBackwardCompatibilityWrapper(match, definedBlockNames)) {
                        return;
                    }

                    context.report({
                        loc: {
                            start: sourceCode.getLocFromIndex(blockMatch.start),
                            end: sourceCode.getLocFromIndex(blockMatch.end),
                        },
                        message: buildMessage(match.migration, match.usage),
                        fix(fixer) {
                            if (context.options.includes('disableFix') || !match.usage.to) {
                                return null;
                            }

                            return fixer.replaceTextRange(
                                [
                                    blockMatch.start,
                                    blockMatch.end,
                                ],
                                match.usage.to,
                            );
                        },
                    });
                });
            },
        };
    },
};
