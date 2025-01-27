/**
 * @package admin
 */

// Should be kept in sync with phpstan-type in src/Core/Framework/Log/Package.php
const VALID_DOMAINS = [
    // ToDo: exclude the old areas
    'buyers-experience',
    'services-settings',
    'administration',
    'data-services',
    'innovation',

    // new domains starting at 2025
    'framework',
    'inventory',
    'discovery',
    'checkout',
    'after-sales',
    'b2b',
    'fundamentals@framework',
    'fundamentals@discovery',
    'fundamentals@checkout',
    'fundamentals@after-sales',
];
const VALID_DOMAIN_LIST = VALID_DOMAINS.map(s => `'${s}'`).join(', ');

/* eslint-disable max-len */
module.exports = {
    meta: {
        type: 'layout',

        docs: {
            description: 'Each file should have a package annotation',
            recommended: true,
        },

        fixable: "code",
    },
    create(context) {
        const sourceCode = context.getSourceCode();
        const comments = sourceCode.getAllComments();

        // Check if the file is a js, ts, spec.js or spec.ts file
        const isJsFile = context.getFilename().endsWith('.js');
        const isTsFile = context.getFilename().endsWith('.ts');
        const isSpecFile = context.getFilename().endsWith('.spec.js') || context.getFilename().endsWith('.spec.ts');

        // Skip if it's a spec file or not a js/ts file
        if (isSpecFile || (!isJsFile && !isTsFile)) {
            return {};
        }

        // Check every comment in the file
        let hasPackageAnnotation = false;
        let annotationRegex = /(@package|@sw-package)\s+([a-zA-Z0-9-@]+)/i

        for (const comment of comments) {
            if (comment.type !== 'Block') {
                continue;
            }

            const match = comment.value.match(annotationRegex);
            if (match) {
                const annotation = match[1];
                const domain = match[2];
                hasPackageAnnotation = true;

                checkAnnotation(context, sourceCode, comment, annotation, domain);
            }
        }

        if (!hasPackageAnnotation) {
            context.report({
                loc: {
                    start: {line: 1, column: 0},
                    end: {line: 1, column: 0},
                },
                message: `File is missing '@sw-package' annotation`,
            });
        }

        return {};
    },
};

function checkAnnotation(context, sourceCode, comment, annotation, domain) {
    if (!VALID_DOMAINS.includes(domain)) {
        const offsetInComment = comment.value.indexOf(domain);
        const commentStart = comment.range[0] + '/*'.length; // isn't included in value, thus add its length
        const locStart = sourceCode.getLocFromIndex(commentStart + offsetInComment);
        const locEnd = sourceCode.getLocFromIndex(commentStart + offsetInComment + domain.length);

        context.report({
            loc: {
                start: locStart,
                end: locEnd,
            },
            message: `Invalid domain '${domain}'. Must be one of ${VALID_DOMAIN_LIST}`
        });
    }

    if (annotation === '@package') {
        const offsetInComment = comment.value.indexOf(annotation);
        const commentStart = comment.range[0] + '/*'.length; // isn't included in value, thus add its length
        const rangeStart = commentStart + offsetInComment;
        const locStart = sourceCode.getLocFromIndex(rangeStart);
        const rangeEnd = commentStart + offsetInComment + annotation.length;
        const locEnd = sourceCode.getLocFromIndex(rangeEnd);

        context.report({
            loc: {
                start: locStart,
                end: locEnd,
            },
            message: `Use '@sw-package' instead of '@package'`,
            fix: function (fixer) {
                return fixer.replaceTextRange([rangeStart, rangeEnd], '@sw-package');
            },
        });
    }
}
