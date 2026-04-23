import fs from 'node:fs';
import path from 'node:path';

const TRIGGER_LABEL = 'testing/test-plan';
const ALLOWED_PARENT_TYPES = new Set(['Epic', 'Story']);

export async function syncTestPlanSubIssue({ github, core, context }) {
    const action = context.payload.action;
    const parentIssue = context.payload.issue;

    if (!parentIssue || parentIssue.pull_request) {
        core.info('Skipping because the payload does not describe a plain issue.');
        return;
    }

    if (action === 'reopened') {
        const hasTriggerLabel = parentIssue.labels.some((label) => label.name === TRIGGER_LABEL);

        if (!hasTriggerLabel) {
            core.info(`Skipping reopened issue #${parentIssue.number} because ${TRIGGER_LABEL} is not present.`);
            return;
        }
    }

    const owner = context.repo.owner;
    const repo = context.repo.repo;
    const parentRef = `${owner}/${repo}#${parentIssue.number}`;
    const marker = `<!-- test-plan-for: ${parentRef} -->`;
    const markerSearchTerm = `test-plan-for: ${parentRef}`;
    const childTitle = `[Test Plan] ${parentIssue.title}`;
    const templatePath = path.join(process.env.GITHUB_WORKSPACE, '.github/ISSUE_TEMPLATE/test-plan.md');
    const templateBody = fs.readFileSync(templatePath, 'utf8').trim();

    async function getIssueTypeName() {
        const inlineTypeName = parentIssue.issue_type?.name ?? parentIssue.issueType?.name ?? null;

        if (inlineTypeName) {
            return inlineTypeName;
        }

        const result = await github.graphql(
            `
              query GetIssueType($id: ID!) {
                node(id: $id) {
                  ... on Issue {
                    issueType {
                      name
                    }
                  }
                }
              }
            `,
            { id: parentIssue.node_id },
        );

        return result?.node?.issueType?.name ?? null;
    }

    async function getLinkedSubIssues() {
        const response = await github.request('GET /repos/{owner}/{repo}/issues/{issue_number}/sub_issues', {
            owner,
            repo,
            issue_number: parentIssue.number,
            per_page: 100,
            headers: {
                'X-GitHub-Api-Version': '2022-11-28',
            },
        });

        return response.data;
    }

    async function searchChildrenByMarker() {
        const searchResponse = await github.rest.search.issuesAndPullRequests({
            q: `repo:${owner}/${repo} is:issue in:body "${markerSearchTerm}"`,
            per_page: 10,
        });

        const exactMatches = [];

        for (const candidate of searchResponse.data.items) {
            if (candidate.pull_request) {
                continue;
            }

            const issueResponse = await github.rest.issues.get({
                owner,
                repo,
                issue_number: candidate.number,
            });

            if (issueResponse.data.body?.includes(marker)) {
                exactMatches.push(issueResponse.data);
            }
        }

        return exactMatches;
    }

    async function findMatchingChildren() {
        const matches = new Map();
        const linkedSubIssues = await getLinkedSubIssues();

        for (const linkedSubIssue of linkedSubIssues) {
            const issueResponse = await github.rest.issues.get({
                owner,
                repo,
                issue_number: linkedSubIssue.number,
            });

            if (issueResponse.data.body?.includes(marker)) {
                matches.set(issueResponse.data.id, issueResponse.data);
            }
        }

        const searchedIssues = await searchChildrenByMarker();

        for (const searchedIssue of searchedIssues) {
            matches.set(searchedIssue.id, searchedIssue);
        }

        return [...matches.values()];
    }

    async function reportConflict(children) {
        const issueLinks = children
            .map((child) => `- #${child.number}`)
            .join('\n');

        await github.rest.issues.createComment({
            owner,
            repo,
            issue_number: parentIssue.number,
            body: [
                'Unable to synchronize the test-plan sub-issue automatically because multiple matching child issues were found:',
                '',
                issueLinks,
                '',
                `Marker: \`${marker}\``,
            ].join('\n'),
        });
    }

    async function createChildIssue() {
        const response = await github.rest.issues.create({
            owner,
            repo,
            title: childTitle,
            body: [
                marker,
                '',
                '## Parent issue',
                '',
                `- ${parentRef}`,
                `- ${parentIssue.html_url}`,
                '',
                templateBody,
            ].join('\n'),
        });

        return response.data;
    }

    async function ensureChildIsOpen(childIssue) {
        if (childIssue.state === 'open') {
            return childIssue;
        }

        const response = await github.rest.issues.update({
            owner,
            repo,
            issue_number: childIssue.number,
            state: 'open',
        });

        return response.data;
    }

    async function closeChildIssue(childIssue) {
        if (childIssue.state === 'closed') {
            core.info(`Test-plan issue #${childIssue.number} is already closed.`);
            return;
        }

        await github.rest.issues.update({
            owner,
            repo,
            issue_number: childIssue.number,
            state: 'closed',
            state_reason: 'not_planned',
        });
    }

    async function getParentOfChild(childIssue) {
        try {
            const response = await github.request('GET /repos/{owner}/{repo}/issues/{issue_number}/parent', {
                owner,
                repo,
                issue_number: childIssue.number,
                headers: {
                    'X-GitHub-Api-Version': '2022-11-28',
                },
            });

            return response.data;
        } catch (error) {
            if (error.status === 404) {
                return null;
            }

            throw error;
        }
    }

    async function ensureSubIssueLink(childIssue) {
        const currentParent = await getParentOfChild(childIssue);

        if (currentParent?.number === parentIssue.number) {
            core.info(`Test-plan issue #${childIssue.number} is already linked to parent issue #${parentIssue.number}.`);
            return;
        }

        if (currentParent && currentParent.number !== parentIssue.number) {
            throw new Error(`Test-plan issue #${childIssue.number} is already linked to #${currentParent.number}.`);
        }

        await github.request('POST /repos/{owner}/{repo}/issues/{issue_number}/sub_issues', {
            owner,
            repo,
            issue_number: parentIssue.number,
            sub_issue_id: childIssue.id,
            headers: {
                'X-GitHub-Api-Version': '2022-11-28',
            },
        });
    }

    const matchingChildren = await findMatchingChildren();

    if (matchingChildren.length > 1) {
        await reportConflict(matchingChildren);
        core.setFailed(`Found ${matchingChildren.length} matching test-plan issues for ${parentRef}.`);
        return;
    }

    if (action === 'unlabeled') {
        if (matchingChildren.length === 0) {
            core.info(`No existing test-plan issue found for ${parentRef}.`);
            return;
        }

        await closeChildIssue(matchingChildren[0]);

        return;
    }

    const parentTypeName = await getIssueTypeName();

    if (!parentTypeName || !ALLOWED_PARENT_TYPES.has(parentTypeName)) {
        core.info(`Skipping issue #${parentIssue.number} because its type is ${parentTypeName ?? 'unknown'} instead of Epic or Story.`);
        return;
    }

    const childIssue = matchingChildren[0] ?? await createChildIssue();
    const openChildIssue = await ensureChildIsOpen(childIssue);

    await ensureSubIssueLink(openChildIssue);
}
