/**
 * Keep the `needs-triage` label in step with the domain labels.
 *
 * An issue or pull request without a `domain/` or `service/` label needs triage, one with
 * such a label does not. The label is added when an item is opened (after the labeler had
 * its five minutes to apply a domain label) and removed the moment someone assigns a
 * `domain/` or `service/` label, so nobody has to remove it by hand. A label the labeler
 * adds with the workflow token fires no `labeled` event, which is why the opened run waits
 * and the nightly `cleanupNeedsTriage` job stays as the safety net.
 *
 * Removing the last domain label adds it back. That run waits a minute first, so swapping
 * one domain label for another does not add and remove the label in quick succession. For
 * the same reason a run for an added label only ever removes `needs-triage`.
 *
 * Drafts, closed items and `[Test Plan]` issues never receive the label. Removal is not
 * gated the same way: a domain label makes `needs-triage` wrong wherever it sits.
 */

export const NEEDS_TRIAGE_LABEL = 'needs-triage';

export type NeedsTriageVerdict =
    | { action: 'add' }
    | { action: 'remove' }
    | { action: 'keep' }
    | { action: 'skip'; reason: string };

export type NeedsTriageInput = {
    /** The label of a `labeled` or `unlabeled` event, absent for every other action. */
    changedLabel?: { name: string; removed: boolean };
    labels: string[];
    isDraft: boolean;
    isClosed: boolean;
    title: string;
};

export function isDomainLabel(name: string): boolean {
    return name.startsWith('domain/') || name.startsWith('service/');
}

export function evaluateNeedsTriage({ changedLabel, labels, isDraft, isClosed, title }: NeedsTriageInput): NeedsTriageVerdict {
    if (changedLabel !== undefined && !isDomainLabel(changedLabel.name)) {
        return { action: 'skip', reason: `label ${changedLabel.name} is no domain label` };
    }

    const hasDomainLabel = labels.some(isDomainLabel);
    const hasNeedsTriage = labels.includes(NEEDS_TRIAGE_LABEL);

    if (hasDomainLabel) {
        return { action: hasNeedsTriage ? 'remove' : 'keep' };
    }

    // The added domain label is gone again; the run for its removal decides after its wait.
    if (changedLabel?.removed === false) {
        return { action: 'keep' };
    }

    if (isDraft) {
        return { action: 'skip', reason: 'pull request is a draft' };
    }

    if (isClosed) {
        return { action: 'skip', reason: 'item is closed' };
    }

    if (title.startsWith('[Test Plan]')) {
        return { action: 'skip', reason: 'test plan issues are not triaged' };
    }

    return { action: hasNeedsTriage ? 'keep' : 'add' };
}

type Label = { name: string };

type Item = {
    number: number;
    state: string;
    title: string;
    draft?: boolean;
};

type NeedsTriageContext = {
    repo: { owner: string; repo: string };
    payload: {
        action?: string;
        label?: Label;
        issue?: Item;
        pull_request?: Item;
    };
};

type LabelOptions = { owner: string; repo: string; issue_number: number };

type IssuesClient = {
    rest: {
        issues: {
            listLabelsOnIssue(options: LabelOptions): Promise<{ data: Label[] }>;
            addLabels(options: LabelOptions & { labels: string[] }): Promise<unknown>;
            removeLabel(options: LabelOptions & { name: string }): Promise<unknown>;
        };
    };
};

type Logger = { info(message: string): void };

type Toolkit = { github: IssuesClient; core: Logger; context: NeedsTriageContext };

function isNotFound(error: unknown): boolean {
    return typeof error === 'object' && error !== null && 'status' in error && error.status === 404;
}

function itemOf(context: NeedsTriageContext): Item {
    const item = context.payload.issue ?? context.payload.pull_request;
    if (!item) {
        throw new Error('The event carries neither an issue nor a pull request.');
    }

    return item;
}

export async function manageNeedsTriage({ github, core, context }: Toolkit): Promise<NeedsTriageVerdict> {
    const item = itemOf(context);
    const options = { owner: context.repo.owner, repo: context.repo.repo, issue_number: item.number };

    // The payload carries labels too, but the API answer reflects what the labeler did meanwhile.
    const { data } = await github.rest.issues.listLabelsOnIssue(options);
    const { action, label } = context.payload;
    const verdict = evaluateNeedsTriage({
        changedLabel: (action === 'labeled' || action === 'unlabeled') && label ? { name: label.name, removed: action === 'unlabeled' } : undefined,
        labels: data.map((label) => label.name),
        isDraft: item.draft ?? false,
        isClosed: item.state === 'closed',
        title: item.title,
    });

    if (verdict.action === 'add') {
        await github.rest.issues.addLabels({ ...options, labels: [NEEDS_TRIAGE_LABEL] });
    } else if (verdict.action === 'remove') {
        try {
            await github.rest.issues.removeLabel({ ...options, name: NEEDS_TRIAGE_LABEL });
        } catch (error) {
            // A run for another domain label added at the same time removed it first.
            if (!isNotFound(error)) {
                throw error;
            }
        }
    }

    core.info(verdict.action === 'skip' ? `#${item.number}: skipped, ${verdict.reason}` : `#${item.number}: ${verdict.action} ${NEEDS_TRIAGE_LABEL}`);

    return verdict;
}
