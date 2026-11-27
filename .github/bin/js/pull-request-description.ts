/**
 * Fails pull requests whose description is empty or too short to explain the change.
 *
 * Only the text the author wrote counts: HTML comments and every line the pull request template
 * already ships (headings, checklist, hints) are boilerplate and are ignored, so an untouched
 * template is treated the same as an empty description.
 */

import { existsSync, readFileSync } from 'node:fs';

export const MIN_LENGTH = 50;

type Logger = { setFailed(message: string): void; info(message: string): void };
type PullRequestContext = { payload: { pull_request?: { body?: string | null } } };

/** Removes HTML comments until none is left, so a comment wrapped in another comment leaves no `<!--` behind. */
const stripComments = (text: string): string => {
    let stripped = text;
    let previous: string;
    do {
        previous = stripped;
        stripped = stripped.replace(/<!--[\s\S]*?-->/g, '');
    } while (stripped !== previous);

    return stripped;
};

const lines = (text: string): string[] => stripComments(text).split(/\r\n|\r|\n/);

/** The lines the template ships verbatim, trimmed, without comments and blank lines. */
export function templateLines(template: string): string[] {
    return lines(template)
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

/** The description without HTML comments and without the lines the template ships verbatim. */
export function authoredText(body: string, template: string): string {
    const boilerplate = new Set(templateLines(template));
    const authored: string[] = [];

    for (const raw of lines(body)) {
        // a ticked checklist item is still the template's line, not authored text
        const line = raw.trim().replace(/^- \[[xX]\]/, '- [ ]');
        if (line === '' || boilerplate.has(line)) {
            continue;
        }
        authored.push(line);
    }

    return authored.join(' ');
}

/** The failure message, or undefined when the description passes. */
export function evaluateDescription(body: string, template: string): string | undefined {
    const description = authoredText(body, template);
    if (description === '') {
        return 'The pull request has no description. Please fill in the pull request template: why the change is necessary and what it does exactly.';
    }

    const length = [...description].length;
    if (length < MIN_LENGTH) {
        return `The pull request description is too short (${length} of at least ${MIN_LENGTH} characters). Please explain why the change is necessary and what it does exactly.`;
    }

    return undefined;
}

/** Entry point for actions/github-script: reads the template of the checked-out base branch. */
export function checkPullRequestDescription({
    core,
    context,
    templatePath = '.github/PULL_REQUEST_TEMPLATE.md',
}: {
    core: Logger;
    context: PullRequestContext;
    templatePath?: string;
}): void {
    const template = existsSync(templatePath) ? readFileSync(templatePath, 'utf8') : '';
    const failure = evaluateDescription(context.payload.pull_request?.body ?? '', template);

    if (failure !== undefined) {
        core.setFailed(failure);

        return;
    }

    core.info('The pull request description is filled in.');
}
