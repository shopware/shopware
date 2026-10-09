/**
 * Mirror the waiting-on verdicts into an organization project, so a board can group the
 * open pull requests by who they are waiting on and a view can filter them by team label.
 *
 * The project is the one named by `WAITING_ON_PROJECT_NUMBER`; it gets every pull request.
 * `WAITING_ON_TEAM_PROJECTS` adds one project per team label, which gets only the pull
 * requests carrying that label. Every project needs three fields, set up by hand once: `Waiting on` and `Reason` as single selects whose options match the
 * verdict and reason codes, and `Waiting since` as a date. A missing field fails the run;
 * a missing option only skips that one value.
 *
 * There is deliberately no field for the age in days. It would change on every item every
 * day, which is ~300 writes a run for nothing a sort on `Waiting since` cannot show.
 *
 * Only this repository's pull requests are touched. Items the sweep did not produce — a
 * pull request that closed, one a bot opened — are archived rather than deleted. The item
 * list leaves archived items out, so a reopened pull request is planned as an add, and
 * `addProjectV2ItemById` answers that by unarchiving the existing item.
 *
 * The item list also lags writes by a minute or two. Nothing here reads back what it just
 * wrote, and an add that lands on an item the list did not show yet is harmless.
 */

import type { Row } from './waiting-on.ts';

export const PROJECT_FIELD = {
    waitingOn: 'Waiting on',
    reason: 'Reason',
    since: 'Waiting since',
} as const;

type FieldKey = keyof typeof PROJECT_FIELD;

export type FieldValues = Partial<Record<FieldKey, string>>;

export type ProjectItem = {
    id: string;
    pullRequestId: string;
    values: FieldValues;
};

export type ProjectChange =
    | { kind: 'add'; row: Row; set: FieldValues }
    | { kind: 'update'; itemId: string; row: Row; set: FieldValues }
    | { kind: 'archive'; itemId: string };

/** The date a verdict flipped, in the `YYYY-MM-DD` form a date field stores. */
export function toDate(timestamp: string): string {
    return timestamp.slice(0, 10);
}

export function valuesOf(row: Row): Required<FieldValues> {
    return { waitingOn: row.waitingOn, reason: row.reason, since: toDate(row.since) };
}

/** A team's own project is curated by the team, so what the sweep did not produce is left alone there. */
export type PlanOptions = { archive?: boolean };

export function parseTeamProjects(json: string | undefined): Map<string, number> {
    if (json === undefined || json.trim() === '') {
        return new Map();
    }

    const parsed: unknown = JSON.parse(json);
    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed) || !Object.values(parsed).every((value) => Number.isInteger(value) && (value as number) > 0)) {
        throw new Error('WAITING_ON_TEAM_PROJECTS must be a JSON object from a label to a project number.');
    }

    return new Map(Object.entries(parsed as Record<string, number>));
}

export function planProjectChanges(rows: Row[], items: ProjectItem[], { archive = true }: PlanOptions = {}): ProjectChange[] {
    const itemsByPullRequest = new Map(items.map((item) => [item.pullRequestId, item]));
    const changes: ProjectChange[] = [];

    for (const row of rows) {
        const wanted = valuesOf(row);
        const item = itemsByPullRequest.get(row.id);

        if (item === undefined) {
            changes.push({ kind: 'add', row, set: wanted });
            continue;
        }

        const set: FieldValues = {};
        for (const key of Object.keys(wanted) as FieldKey[]) {
            if (item.values[key] !== wanted[key]) {
                set[key] = wanted[key];
            }
        }

        if (Object.keys(set).length > 0) {
            changes.push({ kind: 'update', itemId: item.id, row, set });
        }
    }

    if (!archive) {
        return changes;
    }

    const current = new Set(rows.map((row) => row.id));
    for (const item of items) {
        if (!current.has(item.pullRequestId)) {
            changes.push({ kind: 'archive', itemId: item.id });
        }
    }

    return changes;
}

type Field = { id: string; name: string; dataType: string; options?: { id: string; name: string }[] };

export type ProjectSchema = {
    projectId: string;
    fields: Record<FieldKey, Field>;
};

const FIELD_TYPES: Record<FieldKey, string> = {
    waitingOn: 'SINGLE_SELECT',
    reason: 'SINGLE_SELECT',
    since: 'DATE',
};

export function resolveSchema(projectId: string, fields: Field[]): ProjectSchema {
    const resolved = {} as Record<FieldKey, Field>;

    for (const key of Object.keys(PROJECT_FIELD) as FieldKey[]) {
        const field = fields.find((candidate) => candidate.name === PROJECT_FIELD[key]);
        if (field === undefined || field.dataType !== FIELD_TYPES[key]) {
            throw new Error(`The project needs a ${FIELD_TYPES[key]} field named "${PROJECT_FIELD[key]}".`);
        }
        resolved[key] = field;
    }

    return { projectId, fields: resolved };
}

type ItemNode = {
    id: string;
    content: { id?: string; repository?: { nameWithOwner: string } } | null;
    fieldValues: { nodes: { name?: string; date?: string; field?: { name?: string } }[] };
};

export function toProjectItems(nodes: ItemNode[], nameWithOwner: string): ProjectItem[] {
    const items: ProjectItem[] = [];

    for (const node of nodes) {
        if (node.content?.id === undefined || node.content.repository?.nameWithOwner !== nameWithOwner) {
            continue;
        }

        const values: FieldValues = {};
        for (const value of node.fieldValues.nodes) {
            const key = (Object.keys(PROJECT_FIELD) as FieldKey[]).find((candidate) => PROJECT_FIELD[candidate] === value.field?.name);
            const stored = value.name ?? value.date;
            if (key !== undefined && stored !== undefined) {
                values[key] = key === 'since' ? toDate(stored) : stored;
            }
        }

        items.push({ id: node.id, pullRequestId: node.content.id, values });
    }

    return items;
}

const PROJECT_QUERY = `
    query($owner: String!, $number: Int!, $after: String) {
        organization(login: $owner) {
            projectV2(number: $number) {
                id
                fields(first: 50) {
                    nodes {
                        ... on ProjectV2FieldCommon { id name dataType }
                        ... on ProjectV2SingleSelectField { options { id name } }
                    }
                }
                items(first: 100, after: $after) {
                    pageInfo { hasNextPage endCursor }
                    nodes {
                        id
                        content { ... on PullRequest { id repository { nameWithOwner } } }
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

type ProjectClient = {
    graphql<T>(query: string, variables: Record<string, unknown>): Promise<T>;
};

type Core = {
    info(message: string): void;
    warning(message: string): void;
    error(message: string): void;
};

type ProjectPage = {
    organization: {
        projectV2: {
            id: string;
            fields: { nodes: Field[] };
            items: { pageInfo: { hasNextPage: boolean; endCursor: string | null }; nodes: ItemNode[] };
        } | null;
    };
};

export async function fetchProject(github: ProjectClient, owner: string, number: number, nameWithOwner: string): Promise<{ schema: ProjectSchema; items: ProjectItem[] }> {
    const items: ProjectItem[] = [];
    let schema: ProjectSchema | undefined;
    let after: string | undefined = undefined;

    do {
        const result: ProjectPage = await github.graphql(PROJECT_QUERY, { owner, number, after });
        const project = result.organization.projectV2;
        if (project === null) {
            throw new Error(`Project ${owner}/${number} does not exist or the token cannot read it.`);
        }

        schema ??= resolveSchema(project.id, project.fields.nodes.filter((field) => field.id !== undefined));
        items.push(...toProjectItems(project.items.nodes, nameWithOwner));
        after = project.items.pageInfo.hasNextPage ? project.items.pageInfo.endCursor ?? undefined : undefined;
    } while (after);

    return { schema: schema!, items };
}

/**
 * One request per item: GraphQL aliases let the field updates share it. A value whose
 * single-select option does not exist is left out with a warning instead of failing the item.
 */
export function buildFieldMutation(schema: ProjectSchema, itemId: string, set: FieldValues, core: Pick<Core, 'warning'>): { query: string; variables: Record<string, unknown> } | undefined {
    const parts: string[] = [];
    const variables: Record<string, unknown> = { projectId: schema.projectId, itemId };
    const declarations = ['$projectId: ID!', '$itemId: ID!'];

    for (const key of Object.keys(set) as FieldKey[]) {
        const field = schema.fields[key];
        let value: string;

        if (key === 'since') {
            value = `{ date: $${key} }`;
            declarations.push(`$${key}: Date!`);
            variables[key] = set[key];
        } else {
            const option = field.options?.find((candidate) => candidate.name === set[key]);
            if (option === undefined) {
                core.warning(`The "${field.name}" field has no option "${set[key]}"; add it to the project.`);
                continue;
            }
            value = `{ singleSelectOptionId: $${key} }`;
            declarations.push(`$${key}: String!`);
            variables[key] = option.id;
        }

        declarations.push(`$${key}Field: ID!`);
        variables[`${key}Field`] = field.id;
        parts.push(`${key}: updateProjectV2ItemFieldValue(input: { projectId: $projectId, itemId: $itemId, fieldId: $${key}Field, value: ${value} }) { clientMutationId }`);
    }

    if (parts.length === 0) {
        return undefined;
    }

    return { query: `mutation(${declarations.join(', ')}) { ${parts.join(' ')} }`, variables };
}

const ADD_ITEM = `
    mutation($projectId: ID!, $contentId: ID!) {
        addProjectV2ItemById(input: { projectId: $projectId, contentId: $contentId }) { item { id } }
    }
`;

const ARCHIVE_ITEM = `
    mutation($projectId: ID!, $itemId: ID!) {
        archiveProjectV2Item(input: { projectId: $projectId, itemId: $itemId }) { clientMutationId }
    }
`;

const sleep = (milliseconds: number) => new Promise((resolve) => setTimeout(resolve, milliseconds));

/** Writes are spaced out for the same reason as the label writes: the first run adds every open pull request. */
export async function applyProjectChanges(github: ProjectClient, core: Core, schema: ProjectSchema, changes: ProjectChange[], pauseMilliseconds = 1000): Promise<string[]> {
    const failed: string[] = [];
    const { projectId } = schema;

    const write = async (query: string, variables: Record<string, unknown>) => {
        const result = await github.graphql(query, variables);
        await sleep(pauseMilliseconds);

        return result;
    };

    for (const change of changes) {
        const subject = change.kind === 'archive' ? `item ${change.itemId}` : `#${change.row.number}`;

        // One item failing must not hide the rest.
        try {
            let itemId: string;

            if (change.kind === 'archive') {
                await write(ARCHIVE_ITEM, { projectId, itemId: change.itemId });
                core.info(`Archived ${subject}`);
                continue;
            }

            if (change.kind === 'add') {
                const result = (await write(ADD_ITEM, { projectId, contentId: change.row.id })) as { addProjectV2ItemById: { item: { id: string } } };
                itemId = result.addProjectV2ItemById.item.id;
                core.info(`Added ${subject}`);
            } else {
                itemId = change.itemId;
            }

            const mutation = buildFieldMutation(schema, itemId, change.set, core);
            if (mutation !== undefined) {
                await write(mutation.query, mutation.variables);
                core.info(`Set ${Object.entries(change.set).map(([key, value]) => `${key}=${value}`).join(', ')} on ${subject}`);
            }
        } catch (error) {
            failed.push(subject);
            core.error(`Failed to sync ${subject} to the project: ${error instanceof Error ? error.message : String(error)}`);
        }
    }

    return failed;
}

export function summarizeProjectChanges(changes: ProjectChange[]): string {
    const count = (kind: ProjectChange['kind']) => changes.filter((change) => change.kind === kind).length;

    return `${count('add')} added, ${count('update')} updated, ${count('archive')} archived`;
}
