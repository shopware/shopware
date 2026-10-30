/**
 * Re-applies the waiting-on rules to one pull request as soon as something happens to it, so
 * its label and project fields do not wait for the daily sweep in waiting-on.ts. The sweep
 * stays: it is what notices a verdict that changes only because time passes, and it sends the
 * Slack reminders, which this never does.
 *
 * The rules are the sweep's: the same fields are read and the same functions decide. Only the
 * project items are found differently, through the pull request's own `projectItems`, so a
 * single update does not read a whole board.
 */

import { existsSync, readFileSync } from 'node:fs';
import {
    applyLabelChanges,
    buildRows,
    type Context,
    type Core,
    type GraphqlClient,
    type IssuesClient,
    planLabelChanges,
    PULL_REQUEST_FIELDS,
    type PullRequestNode,
    resolveMergeability,
    type Row,
} from './waiting-on.ts';
import {
    applyProjectChanges,
    fetchSchema,
    type FieldValueNode,
    parseTeamProjects,
    planProjectChanges,
    type ProjectChange,
    type ProjectClient,
    type ProjectItem,
    readValues,
    summarizeProjectChanges,
} from './waiting-on-project.ts';

/**
 * The number comes from the event, or, for a review relayed through workflow_run, from the
 * relay's artifact. The artifact is written by a workflow a fork can change, so it is parsed
 * strictly; the worst a forged number can do is update another pull request correctly.
 */
export function resolvePullRequestNumber(fromEvent: string | undefined, relayFile: string | undefined): number {
    const raw = (relayFile !== undefined && existsSync(relayFile) ? readFileSync(relayFile, 'utf8') : fromEvent ?? '').trim();
    if (!/^[1-9]\d*$/.test(raw)) {
        throw new Error(`Not a pull request number: "${raw.slice(0, 40)}"`);
    }

    return Number(raw);
}

const PULL_REQUEST_QUERY = `
    query($owner: String!, $repo: String!, $number: Int!) {
        repository(owner: $owner, name: $repo) {
            pullRequest(number: $number) { state ${PULL_REQUEST_FIELDS} }
        }
    }
`;

const PROJECT_ITEMS_QUERY = `
    query($id: ID!) {
        node(id: $id) {
            ... on PullRequest {
                projectItems(first: 50, includeArchived: true) {
                    nodes {
                        id
                        isArchived
                        project { number owner { ... on Organization { login } } }
                        fieldValues(first: 20) {
                            nodes {
                                ... on ProjectV2ItemFieldSingleSelectValue { name field { ... on ProjectV2FieldCommon { name } } }
                                ... on ProjectV2ItemFieldDateValue { date field { ... on ProjectV2FieldCommon { name } } }
                            }
                        }
                    }
                }
            }
        }
    }
`;

export type ItemOnProject = ProjectItem & { isArchived: boolean; owner: string; projectNumber: number };

type ProjectItemNode = {
    id: string;
    isArchived: boolean;
    project: { number: number; owner: { login?: string } };
    fieldValues: { nodes: FieldValueNode[] };
};

export function toItemsOnProjects(nodes: ProjectItemNode[], pullRequestId: string): ItemOnProject[] {
    return nodes.map((node) => ({
        id: node.id,
        pullRequestId,
        values: readValues(node.fieldValues.nodes),
        isArchived: node.isArchived,
        owner: node.project.owner.login ?? '',
        projectNumber: node.project.number,
    }));
}

/**
 * The sweep's plan, narrowed to one pull request. An archived item counts as missing, so an
 * open pull request is added again, which unarchives it. Without a row — the pull request is
 * closed, or a bot opened it — the item is archived where the sweep would archive it.
 */
export function planPullRequestSync(row: Row | undefined, item: ItemOnProject | undefined, archive: boolean): ProjectChange[] {
    if (row !== undefined) {
        return planProjectChanges([row], item !== undefined && !item.isArchived ? [item] : [], { archive: false });
    }

    return archive && item !== undefined && !item.isArchived ? [{ kind: 'archive', itemId: item.id }] : [];
}

export type SyncOptions = {
    number: number;
    dryRun?: boolean;
    /** As in the sweep: the main project gets every pull request, `teams` maps a label to a project. */
    project?: { github: ProjectClient; number: number; teams?: string };
};

export async function syncPullRequest({ github, core, context }: { github: GraphqlClient & IssuesClient; core: Core; context: Context }, { number, dryRun = false, project }: SyncOptions): Promise<void> {
    const { owner } = context.repo;
    const result = await github.graphql<{ repository: { pullRequest: (PullRequestNode & { state: string }) | null } }>(PULL_REQUEST_QUERY, { ...context.repo, number });
    const node = result.repository.pullRequest;
    if (node === null) {
        throw new Error(`#${number} is not a pull request in ${owner}/${context.repo.repo}.`);
    }

    const failures: string[] = [];
    let row: Row | undefined;

    // A closed pull request keeps its last label, as the sweep only ever looks at open ones.
    if (node.state === 'OPEN') {
        await resolveMergeability(github, core, context.repo, [node]);
        [row] = buildRows([node], new Date());
        core.info(row !== undefined ? `#${number}: ${row.label} (${row.reason}) since ${row.since}` : `#${number}: no verdict, a bot opened it`);

        const changes = planLabelChanges([node], row !== undefined ? [row] : []);
        if (!dryRun && (await applyLabelChanges(github, core, context.repo, changes, 0)).length > 0) {
            failures.push('the waiting-on label');
        }
    } else {
        core.info(`#${number} is ${node.state.toLowerCase()}`);
    }

    if (project !== undefined) {
        const labels = node.labels.nodes.map((label) => label.name);
        const targets = [
            { number: project.number, archive: true },
            ...[...parseTeamProjects(project.teams)].filter(([teamLabel]) => labels.includes(teamLabel)).map(([, teamProject]) => ({ number: teamProject, archive: false })),
        ];

        const items = toItemsOnProjects(
            (await project.github.graphql<{ node: { projectItems: { nodes: ProjectItemNode[] } } }>(PROJECT_ITEMS_QUERY, { id: node.id })).node.projectItems.nodes,
            node.id,
        );

        for (const target of targets) {
            // One project failing must not keep the others from their update.
            try {
                const item = items.find((candidate) => candidate.owner === owner && candidate.projectNumber === target.number);
                const changes = planPullRequestSync(row, item, target.archive);
                core.info(`Project ${owner}/${target.number}${dryRun ? ' (dry run, nothing written)' : ''}: ${summarizeProjectChanges(changes)}.`);

                if (!dryRun && changes.length > 0) {
                    const schema = await fetchSchema(project.github, owner, target.number);
                    failures.push(...(await applyProjectChanges(project.github, core, schema, changes, 0)));
                }
            } catch (error) {
                failures.push(`project ${target.number}`);
                core.error(error instanceof Error ? error.message : String(error));
            }
        }
    }

    if (failures.length > 0) {
        throw new Error(`Failed to update #${number}: ${failures.join(', ')}`);
    }
}
