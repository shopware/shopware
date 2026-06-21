/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function buildMessage(migration, usage) {
    const references = formatReferences(migration);
    const replacement = usage.to ? ` Use "${usage.to}" instead.` : ' Remove or move this customization.';

    return [
        `The Administration Twig block "${usage.from}" is deprecated.${replacement}`,
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function collectBlockMatches(sourceText) {
    const matches = [];
    const twigBlockPattern = /\{%\s*block\s+([A-Za-z0-9_]+)\s*%}/g;
    const swBlockPattern = /<sw-block\b[^>]*\s(?:name|extends)="([^"]+)"/g;

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
        matches.push({
            name: match[1],
            start: match.index + match[0].indexOf(match[1]),
            end: match.index + match[0].indexOf(match[1]) + match[1].length,
        });

        match = swBlockPattern.exec(sourceText);
    }

    return matches;
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

                collectBlockMatches(sourceText).forEach((blockMatch) => {
                    const match = usages.find(({ usage }) => usage.from === blockMatch.name);

                    if (!match) {
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
