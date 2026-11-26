/**
 * Move new issues that a team board filed under a copied issue status back into its triage column.
 *
 * GitHub's issue statuses (public preview) give every new issue the issue status `Backlog`. When a
 * board's auto-add workflow puts such an issue on a board that has a column of the same name,
 * GitHub copies the issue status into the board's Status, and the board's own "Item added to
 * project" workflow no longer sets the triage column. The issue lands in `Backlog` and skips the
 * team's triage.
 *
 * The sweep moves an item back when it is an open issue, was added within the lookback window,
 * sits in one of the copied columns, got a copied status as its very first status on that board,
 * and only `github-project-automation` ever set its status there. An item that started in triage
 * was seen by the team, whatever the automation did to it later (a reopened issue also lands in
 * `Backlog`). A status any person or app chose, this sweep's own write included, keeps the item
 * where it is, so a run never undoes a triage decision and never moves the same item twice.
 */

export const PROJECT_AUTOMATION_LOGIN = 'github-project-automation';

export type TriageBoard = {
    number: number;
    /** The column the team triages from. */
    triageStatus: string;
    /** Columns named like an issue status, which GitHub fills on add. */
    copiedStatuses: string[];
};

/** One `ProjectV2ItemStatusChangedEvent`, oldest first in `statusChanges`. */
export type StatusChange = { actor: string | null; projectNumber: number | null; status: string };

export type BoardItem = {
    id: string;
    addedAt: string;
    status: string | null;
    /** Null for a pull request, a draft, or an issue the token cannot read. */
    issue: { reference: string; isOpen: boolean; statusChanges: StatusChange[] } | null;
};

export type TriageVerdict = { action: 'move' } | { action: 'skip'; reason: string };

/**
 * Decide whether one item goes back to the triage column. Pure, so every rule is unit-tested;
 * `since` bounds the sweep to recently added items.
 */
export function evaluateItem(item: BoardItem, board: TriageBoard, since: Date): TriageVerdict {
    if (item.issue === null) {
        return { action: 'skip', reason: 'not a readable issue' };
    }

    if (!item.issue.isOpen) {
        return { action: 'skip', reason: 'issue is closed' };
    }

    if (item.status === null || !board.copiedStatuses.includes(item.status)) {
        return { action: 'skip', reason: `status ${item.status ?? 'empty'} is no copied issue status` };
    }

    if (new Date(item.addedAt) < since) {
        return { action: 'skip', reason: 'added before the lookback window' };
    }

    const changesOnBoard = item.issue.statusChanges.filter((change) => change.projectNumber === board.number);
    const chosenBy = changesOnBoard.find((change) => change.actor !== PROJECT_AUTOMATION_LOGIN);
    if (chosenBy !== undefined) {
        return { action: 'skip', reason: `status was set by ${chosenBy.actor ?? 'a deleted account'}` };
    }

    const firstStatus = changesOnBoard[0]?.status;
    if (firstStatus === undefined || !board.copiedStatuses.includes(firstStatus)) {
        return { action: 'skip', reason: `item started in ${firstStatus ?? 'no recorded status'}` };
    }

    return { action: 'move' };
}

/** Builds the project item search, e.g. `is:issue is:open status:"Backlog"`. */
export function itemQuery(board: TriageBoard): string {
    return `is:issue is:open status:${board.copiedStatuses.map((status) => JSON.stringify(status)).join(',')}`;
}

export function parseBoards(json: string | undefined): TriageBoard[] {
    const parsed: unknown = JSON.parse(json ?? '[]');
    const isBoard = (value: unknown): value is TriageBoard =>
        typeof value === 'object' &&
        value !== null &&
        Number.isInteger((value as TriageBoard).number) &&
        typeof (value as TriageBoard).triageStatus === 'string' &&
        Array.isArray((value as TriageBoard).copiedStatuses) &&
        (value as TriageBoard).copiedStatuses.every((status) => typeof status === 'string');

    if (!Array.isArray(parsed) || !parsed.every(isBoard)) {
        throw new Error('TRIAGE_BOARDS must be a JSON list of { number, triageStatus, copiedStatuses }.');
    }

    return parsed;
}

const ITEMS_QUERY = `
query ($org: String!, $number: Int!, $filter: String!, $after: String) {
  organization(login: $org) {
    projectV2(number: $number) {
      id
      field(name: "Status") {
        ... on ProjectV2SingleSelectField { id options { id name } }
      }
      items(first: 50, after: $after, query: $filter) {
        pageInfo { hasNextPage endCursor }
        nodes {
          id
          createdAt
          fieldValueByName(name: "Status") {
            ... on ProjectV2ItemFieldSingleSelectValue { name }
          }
          content {
            ... on Issue {
              number
              state
              repository { nameWithOwner }
              timelineItems(last: 50, itemTypes: [PROJECT_V2_ITEM_STATUS_CHANGED_EVENT]) {
                nodes {
                  ... on ProjectV2ItemStatusChangedEvent { actor { login } project { number } status }
                }
              }
            }
          }
        }
      }
    }
  }
}`;

const SET_STATUS_MUTATION = `
mutation ($project: ID!, $item: ID!, $field: ID!, $option: String!) {
  updateProjectV2ItemFieldValue(
    input: { projectId: $project, itemId: $item, fieldId: $field, value: { singleSelectOptionId: $option } }
  ) { projectV2Item { id } }
}`;

type ItemNode = {
    id: string;
    createdAt: string;
    fieldValueByName: { name?: string } | null;
    content: {
        number?: number;
        state?: string;
        repository?: { nameWithOwner: string };
        timelineItems?: {
            nodes: ({ actor: { login: string } | null; project: { number: number } | null; status: string } | null)[];
        };
    } | null;
};

type ItemsResponse = {
    organization: {
        projectV2: {
            id: string;
            field: { id: string; options: { id: string; name: string }[] } | null;
            items: { pageInfo: { hasNextPage: boolean; endCursor: string | null }; nodes: ItemNode[] };
        } | null;
    };
};

/** Flattens one GraphQL item; anything but a readable issue becomes `issue: null`. */
export function toBoardItem(node: ItemNode): BoardItem {
    const content = node.content;
    const issue =
        content?.number !== undefined && content.repository && content.timelineItems
            ? {
                  reference: `${content.repository.nameWithOwner}#${content.number}`,
                  isOpen: content.state === 'OPEN',
                  statusChanges: content.timelineItems.nodes
                      .filter((event) => event !== null)
                      .map((event) => ({
                          actor: event.actor?.login ?? null,
                          projectNumber: event.project?.number ?? null,
                          status: event.status,
                      })),
              }
            : null;

    return { id: node.id, addedAt: node.createdAt, status: node.fieldValueByName?.name ?? null, issue };
}

export type GraphqlClient = {
    graphql<T>(query: string, variables: Record<string, unknown>): Promise<T>;
};

type Logger = { info(message: string): void };

export type SweepOptions = {
    org: string;
    boards: TriageBoard[];
    dryRun: boolean;
    lookbackDays: number;
    now?: Date;
};

/** Sweeps every configured board once; returns the references of the issues it moved. */
export async function moveCopiedStatusesToTriage(
    { github, core }: { github: GraphqlClient; core: Logger },
    { org, boards, dryRun, lookbackDays, now = new Date() }: SweepOptions,
): Promise<string[]> {
    const since = new Date(now.getTime() - lookbackDays * 24 * 60 * 60 * 1000);
    const moved: string[] = [];

    for (const board of boards) {
        let after: string | null = null;
        do {
            const response: ItemsResponse = await github.graphql<ItemsResponse>(ITEMS_QUERY, {
                org,
                number: board.number,
                filter: itemQuery(board),
                after,
            });
            const project = response.organization.projectV2;
            if (!project) {
                throw new Error(`Project ${org}/${board.number} does not exist or the token cannot read it.`);
            }

            const option = project.field?.options.find(({ name }) => name === board.triageStatus);
            if (!project.field || !option) {
                throw new Error(`Project ${board.number} has no Status option "${board.triageStatus}".`);
            }

            for (const item of project.items.nodes.map(toBoardItem)) {
                const verdict = evaluateItem(item, board, since);
                if (verdict.action === 'skip') {
                    continue;
                }

                const reference = item.issue?.reference ?? item.id;
                core.info(`${dryRun ? '[dry run] ' : ''}${reference}: ${item.status} → ${board.triageStatus}`);
                if (!dryRun) {
                    await github.graphql(SET_STATUS_MUTATION, {
                        project: project.id,
                        item: item.id,
                        field: project.field.id,
                        option: option.id,
                    });
                }
                moved.push(reference);
            }

            after = project.items.pageInfo.hasNextPage ? project.items.pageInfo.endCursor : null;
        } while (after !== null);
    }

    core.info(`${moved.length} item(s) ${dryRun ? 'would move' : 'moved'} to triage.`);

    return moved;
}
