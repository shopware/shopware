/**
 * @sw-package framework
 */

const { getLeadingComments, scriptCodemodComment, templateCodemodComment } = require('./codemod-comment');
const { isFixDisabled } = require('./fix-policy');

/**
 * How much a `--fix` run of the deprecation rules may change, from the least to the most:
 * - `manual`: changes no code and puts a review comment above every deprecated usage.
 * - `safe`: applies only fixes that keep the behaviour, so the result needs no review.
 * - `review`: also applies fixes that leave a review comment, but nothing that leaves work behind.
 * - `partial`: applies everything and puts a review comment above each part a person has to finish.
 */
const RUN_LEVELS = [
    'manual',
    'safe',
    'review',
    'partial',
];

// ESLint `settings` key the run level is read from
const SETTINGS_KEY = 'sw-deprecation-rules';

const REVIEW_MARKER = 'TODO Codemod';

/**
 * Returns the run level from the shared ESLint settings. Without a setting, rules run at `safe`, so a
 * plain `eslint --fix` only applies fixes that keep the behaviour.
 *
 * @example
 * // eslint.config.mjs: settings: { 'sw-deprecation-rules': { level: 'review' } }
 * getRunLevel(context); // 'review'
 */
function getRunLevel(context) {
    const level = context.settings?.[SETTINGS_KEY]?.level ?? 'safe';

    if (!RUN_LEVELS.includes(level)) {
        throw new Error(`Unknown deprecation run level "${level}". Use one of: ${RUN_LEVELS.join(', ')}.`);
    }

    return level;
}

/**
 * The fixer ESLint passes to fix functions, for fixes that are built outside of a report: to classify
 * them, or to combine them into another report's fix.
 *
 * @example
 * collectFixes(descriptor.fix, RANGE_FIXER); // [{ range: [10, 15], text: 'mt-button' }]
 */
const RANGE_FIXER = {
    insertTextAfter: (target, text) => RANGE_FIXER.insertTextAfterRange(target.range, text),
    insertTextAfterRange: (range, text) => ({ range: [range[1], range[1]], text }),
    insertTextBefore: (target, text) => RANGE_FIXER.insertTextBeforeRange(target.range, text),
    insertTextBeforeRange: (range, text) => ({ range: [range[0], range[0]], text }),
    remove: (target) => ({ range: target.range, text: '' }),
    removeRange: (range) => ({ range, text: '' }),
    replaceText: (target, text) => ({ range: target.range, text }),
    replaceTextRange: (range, text) => ({ range, text }),
};

/**
 * Runs a report's fix function and returns its fixes as a flat list; no fix gives an empty list.
 *
 * @example
 * collectFixes((fixer) => [fixer.remove(a), fixer.remove(b)]); // two fix objects
 */
function collectFixes(fix, fixer = RANGE_FIXER) {
    const result = typeof fix === 'function' ? fix(fixer) : null;

    if (!result) {
        return [];
    }

    if (result.range) {
        return [result];
    }

    return [...result].filter(Boolean);
}

/**
 * Tells whether fixes can go into one report: ESLint rejects a report whose fixes overlap.
 *
 * @example
 * haveOverlappingRanges([{ range: [0, 5] }, { range: [3, 8] }]); // true
 */
function haveOverlappingRanges(fixes) {
    const sorted = [...fixes].sort((left, right) => left.range[0] - right.range[0] || left.range[1] - right.range[1]);

    return sorted.some((fix, index) => index > 0 && fix.range[0] < sorted[index - 1].range[1]);
}

const LEVEL_ORDER = [
    'auto',
    'unsafe-auto',
    'manual',
];

/**
 * Classifies a report: `auto` when its fix keeps the behaviour, `unsafe-auto` when the fix needs a
 * review, `manual` when a person has to do the work. A fix that writes a review comment is at least
 * `unsafe-auto`, whatever the report declares, so a run that promises no review never adds one.
 *
 * @example
 * classifyReport({ fix: (fixer) => fixer.replaceText(node, 'x') }); // 'auto'
 * classifyReport({ fix: null }); // 'manual'
 */
function classifyReport(descriptor) {
    const fixes = collectFixes(descriptor.fix);

    if (fixes.length === 0) {
        return 'manual';
    }

    const declared = descriptor.fixLevel ?? 'auto';
    const written = fixes.some((fix) => fix.text.includes(REVIEW_MARKER)) ? 'unsafe-auto' : 'auto';

    return LEVEL_ORDER[Math.max(LEVEL_ORDER.indexOf(declared), LEVEL_ORDER.indexOf(written))];
}

/**
 * Resolves the message of a report that uses `messageId` with `data`, as ESLint would.
 *
 * @example
 * resolveMessage(rule, { messageId: 'noTc', data: { name: '$tc' } }); // 'Use $t instead of $tc …'
 */
function resolveMessage(rule, descriptor) {
    const template = descriptor.message ?? rule.meta?.messages?.[descriptor.messageId] ?? '';

    return template.replace(/\{\{\s*([^{}\s]+)\s*\}\}/g, (placeholder, key) => {
        return descriptor.data && key in descriptor.data ? String(descriptor.data[key]) : placeholder;
    });
}

/**
 * Finds the innermost element whose range contains the index, for reports that only have a location.
 */
function findElementAt(element, index) {
    const child = (element.children ?? []).find((node) => {
        return node.type === 'VElement' && node.range[0] <= index && index < node.range[1];
    });

    return child ? findElementAt(child, index) : element;
}

/**
 * Finds the element a review comment for a report goes above: the reported template node's element,
 * or for a location only, the innermost element around it. The comment moves up to the first parent
 * element that starts its own line and is not changed by `fixes`, so it never splits a line.
 * Returns null outside of the template and for the template root, which the Twig processor adds and
 * no comment can precede.
 */
function findCommentElement(sourceCode, descriptor, fixes) {
    const templateBody = sourceCode.ast?.templateBody;
    let node = descriptor.node;

    if (!node && templateBody) {
        const index = sourceCode.getIndexFromLoc(descriptor.loc.start ?? descriptor.loc);
        node = templateBody.range[0] <= index && index < templateBody.range[1] ? findElementAt(templateBody, index) : null;
    }

    const isInsideFix = (element) => fixes.some((fix) => fix.range[0] < element.range[0] && element.range[0] < fix.range[1]);
    const startsLine = (element) => sourceCode.lines[element.loc.start.line - 1].slice(0, element.loc.start.column).trim() === '';

    while (node && node !== templateBody && (node.type !== 'VElement' || isInsideFix(node) || !startsLine(node))) {
        node = node.parent;
    }

    return node && node !== templateBody ? node : null;
}

/**
 * Builds the fix that puts a review comment with `text` above the reported usage, or null when the
 * same comment is already there. Template usages get an HTML comment above their element, script
 * usages a block comment above their line. `fixes` are the changes the comment goes with.
 *
 * @example
 * createReviewCommentFix(sourceCode, descriptor, '[mt-select] Move the options into the "options" prop.');
 */
function createReviewCommentFix(sourceCode, descriptor, text, fixes = []) {
    const element = findCommentElement(sourceCode, descriptor, fixes);

    if (element) {
        const comment = templateCodemodComment(text.replace(/--+>/g, '->'));
        const exists = getLeadingComments(sourceCode, element).some((leading) => `<!--${leading.value}-->` === comment);

        // The element starts its line, so the text before it is its indentation, tabs included
        const indentation = sourceCode.lines[element.loc.start.line - 1].slice(0, element.loc.start.column);

        return exists ? null : (fixer) => fixer.insertTextBeforeRange(element.range, `${comment}\n${indentation}`);
    }

    const start = descriptor.node?.loc?.start ?? descriptor.loc.start ?? descriptor.loc;
    const line = start.line;
    const lineStart = sourceCode.getIndexFromLoc({ line, column: 0 });
    const indentation = sourceCode.lines[line - 1].match(/^\s*/)[0];
    const templateRange = sourceCode.ast?.templateBody?.range;
    const index = sourceCode.getIndexFromLoc(start);
    const isTemplate = Boolean(templateRange) && templateRange[0] <= index && index < templateRange[1];
    const comment = isTemplate
        ? templateCodemodComment(text.replace(/--+>/g, '->'))
        : scriptCodemodComment(text.replace(/\*\//g, '* /'));

    if (line > 1 && sourceCode.lines[line - 2].trim() === comment) {
        return null;
    }

    return (fixer) => fixer.insertTextBeforeRange([lineStart, lineStart], `${indentation}${comment}\n`);
}

/**
 * Adds the review comment to a fix that needs a review but does not write one, so every change that
 * needs a review is marked in the code. Drops the fix when the comment cannot go into the same report.
 */
function withReviewComment(sourceCode, descriptor, fix, reviewText) {
    const fixes = collectFixes(fix);
    const commentFix = createReviewCommentFix(sourceCode, descriptor, reviewText, fixes);

    if (!commentFix || fixes.some((fixObject) => fixObject.text.includes(REVIEW_MARKER))) {
        return fix;
    }

    const allFixes = [...fixes, ...collectFixes(commentFix)];

    return haveOverlappingRanges(allFixes) ? null : () => allFixes;
}

/**
 * Applies the run level to one report: keeps, drops or replaces its fix with a review comment.
 */
function applyRunLevel(level, sourceCode, descriptor, message) {
    const { fixLevel, reviewComment, conversionNote, ...report } = descriptor;
    const kind = classifyReport(descriptor);
    const reviewText = reviewComment ?? message.split('\n')[0];

    if (level === 'manual' || (level === 'partial' && kind === 'manual')) {
        return { ...report, fix: createReviewCommentFix(sourceCode, descriptor, reviewText) };
    }

    if (kind === 'manual' || (level === 'safe' && kind === 'unsafe-auto')) {
        return { ...report, fix: null };
    }

    return kind === 'unsafe-auto' ? { ...report, fix: withReviewComment(sourceCode, descriptor, report.fix, reviewText) } : report;
}

/**
 * Makes a deprecation rule follow the run level of the ESLint settings. Rules report as usual and may
 * describe a report with two extra fields, which never reach ESLint:
 * - `fixLevel`: what the fix is, when the fix alone does not tell (default `auto`).
 * - `reviewComment`: the text of the review comment for this usage (default: the first message line).
 *
 * @example
 * module.exports = withRunLevel({ meta, create(context) { … } });
 */
function withRunLevel(rule) {
    return {
        ...rule,
        // At `partial` and `manual` every report can get a review comment fix
        meta: { ...rule.meta, fixable: rule.meta?.fixable ?? 'code' },
        create(context) {
            if (isFixDisabled(context)) {
                return rule.create(context);
            }

            const level = getRunLevel(context);
            const runContext = Object.create(context, {
                report: {
                    value: (descriptor) => {
                        const message = resolveMessage(rule, descriptor);

                        context.report(applyRunLevel(level, context.sourceCode, descriptor, message));
                    },
                },
            });

            return rule.create(runContext);
        },
    };
}

module.exports = {
    RANGE_FIXER,
    RUN_LEVELS,
    SETTINGS_KEY,
    classifyReport,
    collectFixes,
    getRunLevel,
    haveOverlappingRanges,
    withRunLevel,
};
