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
 * Drafts, closed items and `[Test Plan]` issues never receive the label. Removal is not
 * gated the same way: a domain label makes `needs-triage` wrong wherever it sits.
 */

export const NEEDS_TRIAGE_LABEL = 'needs-triage';

export type Decision = 'add' | 'remove' | 'keep';

type Label = {
    name: string;
};

type Item = {
    number: number;
    state?: string;
    title?: string;
    draft?: boolean;
};

export type NeedsTriageContext = {
    eventName: string;
    repo: {
        owner: string;
        repo: string;
    };
    payload: {
        action?: string;
        label?: Label;
        issue?: Item;
        pull_request?: Item;
    };
};

type LabelOptions = {
    owner: string;
    repo: string;
    issue_number: number;
};

type GitHubRestClient = {
    rest: {
        issues: {
            listLabelsOnIssue(options: LabelOptions): Promise<{ data: Label[] }>;
            addLabels(options: LabelOptions & { labels: string[] }): Promise<unknown>;
            removeLabel(options: LabelOptions & { name: string }): Promise<unknown>;
        };
    };
};

type CoreLogger = {
    info(message: string): void;
};

export function isDomainLabel(name: string): boolean {
    return name.startsWith('domain/') || name.startsWith('service/');
}

/** Whether this event can change the triage state at all; the reason is returned for the log. */
export function shouldHandle(context: NeedsTriageContext): string | null {
    const { action, label, issue, pull_request: pullRequest } = context.payload;
    const item = issue ?? pullRequest;

    if (!item) {
        return 'no issue or pull request in the payload';
    }

    if (action === 'labeled') {
        return label && isDomainLabel(label.name) ? null : `label ${label?.name ?? '?'} is no domain label`;
    }

    if (pullRequest?.draft) {
        return 'pull request is a draft';
    }

    if (item.state === 'closed') {
        return 'item is closed';
    }

    if (issue?.title?.startsWith('[Test Plan]')) {
        return 'test plan issues are not triaged';
    }

    return null;
}

export function decide(labels: string[]): Decision {
    const hasDomainLabel = labels.some(isDomainLabel);
    const hasNeedsTriage = labels.includes(NEEDS_TRIAGE_LABEL);

    if (hasDomainLabel && hasNeedsTriage) {
        return 'remove';
    }

    if (!hasDomainLabel && !hasNeedsTriage) {
        return 'add';
    }

    return 'keep';
}

export async function manageNeedsTriage({
    github,
    core,
    context,
}: {
    github: GitHubRestClient;
    core: CoreLogger;
    context: NeedsTriageContext;
}): Promise<Decision | null> {
    const reason = shouldHandle(context);
    if (reason !== null) {
        core.info(`Skipping: ${reason}`);
        return null;
    }

    const item = (context.payload.issue ?? context.payload.pull_request) as Item;
    const options = { owner: context.repo.owner, repo: context.repo.repo, issue_number: item.number };

    // The payload carries labels too, but the API answer reflects what the labeler did meanwhile.
    const labels = await github.rest.issues.listLabelsOnIssue(options);
    const decision = decide(labels.data.map((label) => label.name));

    if (decision === 'add') {
        await github.rest.issues.addLabels({ ...options, labels: [NEEDS_TRIAGE_LABEL] });
    } else if (decision === 'remove') {
        await github.rest.issues.removeLabel({ ...options, name: NEEDS_TRIAGE_LABEL });
    }

    core.info(`#${item.number}: ${decision} ${NEEDS_TRIAGE_LABEL}`);

    return decision;
}
