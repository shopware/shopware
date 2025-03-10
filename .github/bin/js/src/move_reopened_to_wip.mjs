/** @type number */
const FRAMEWORK_GROUP_PROJECT_NUMBER = 27;
const IN_PROGRESS_OPTION_NAME = 'In Progress';

/**
 * @param github {import('@octokit/rest').Octokit} Github Octokit instance
 * @param core {import('@actions/core')} for logging
 * @param projectNumber {number} Number of the project
 *
 * @returns {Promise<{node_id: string, priority_field_id: string, priority_options: Array<{id: string, name: string>}}>}
*/
async function getProjectInfo(github, core, projectNumber) {
  const res = await github.graphql(
    `query getProjectInfo($organization: String!, $projectNumber: Int!) {
          organization(login: $organization) {
            projectV2(number: $projectNumber) {
              id
              field(name: "Status") {
                ... on ProjectV2SingleSelectField {
                  id
                  options {
                    id
                    name
                  }
                }
              }
            }
          }
        }
        `,
    {
      organization: "shopware",
      projectNumber: projectNumber,
    }
  )

  core.debug(`getProjectInfo response: ${JSON.stringify(res)}`)

  const project = res.organization.projectV2

  return {
    node_id: project.id,
    status_field_id: project.field.id,
    status_options: project.field.options
  }
}

/**
 * @param github {import('@octokit/rest').Octokit} Github Octokit instance
 * @param core {import('@actions/core')} for logging
 * @param projectId {string} ID of the project
 * @param issueId {string} ID of the issue
 *
 * @returns {Promise<{node_id: string}>}
*/
async function addCard(github, core, projectId, issueId) {
  const res = await github.graphql(
    `mutation addCard($projectId: ID!, $contentId: ID!) {
            addProjectV2ItemById(input: {
              projectId: $projectId,
              contentId: $contentId
            }) {
              item {
                id
              }
            }
          }
        `, {
    projectId: projectId,
    contentId: issueId
  })

  core.debug(`addCard response: ${JSON.stringify(res)}`)

  return {
    node_id: res.addProjectV2ItemById.item.id
  }
}

/**
 * @param github {import('@octokit/rest').Octokit} Github Octokit instance
 * @param core {import('@actions/core')} for logging
 * @param projectId {string} ID of the project
 * @param cardId {string} ID of the card
 * @param fieldId {string} ID of the field
 * @param valueId {string} ID of the value
 *
 * @returns {Promise}
*/
async function setFieldValue(github, core, projectId, cardId, fieldId, valueId) {
  const res = await github.graphql(
    `mutation setFieldValue($projectId: ID!, $itemId: ID!, $fieldId: ID!, $valueId: String!) {
            updateProjectV2ItemFieldValue(input: {
              projectId: $projectId,
              itemId: $itemId,
              fieldId: $fieldId,
              value: {singleSelectOptionId: $valueId}
            }) {
              projectV2Item {
                id
              }
            }
          }`,
    {
      projectId: projectId,
      itemId: cardId,
      fieldId: fieldId,
      valueId: valueId,
    }
  )

  core.debug(`setFieldValue response: ${JSON.stringify(res)}`)
}

async function findInProject(github, core, context, projectNumber) {
  if (context.payload.issue) {
    const res = await github.graphql(
      `query findIssueInProject($projectNumber: Int!, $number: Int!) {
        repository(owner: "shopware", name: "shopware") {
          issue(number: $number) {
            projectV2(number: $projectNumber) {
              url
            }
            id
            number
          }
        }
      }`,
      {
        projectNumber,
        number: context.payload.issue.number,
      }
    )

    core.debug(`findIssueInProject response: ${JSON.stringify(res)}`)

    return {
      node_id: res.repository.issue.id,
      number: res.repository.issue.number,
      project: res.repository.issue.projectV2,
    }
  } else {
    const res = await github.graphql(
      `query findPRInProject($projectNumber: Int!, $number: Int!) {
        repository(owner: "shopware", name: "shopware") {
          pullRequest(number: $number) {
            projectV2(number: $projectNumber) {
              url
            }
            id
            number
          }
        }
      }`,
      {
        projectNumber,
        number: context.payload.pull_request.number,
      }
    )

    core.debug(`findPRInProject response: ${JSON.stringify(res)}`)

    return {
      node_id: res.repository.issue.id,
      number: res.repository.issue.number,
      project: res.repository.issue.projectV2,
    }
  }
}

/**
 * @param github {import('@octokit/rest').Octokit} Github Octokit instance
 * @param core {import('@actions/core')} for logging
 * @param context {import('@actions/github').context} info about the current event
 */
export const main = async (github, core, context) => {
  const issue = await findInProject(github, core, context, FRAMEWORK_GROUP_PROJECT_NUMBER, context.payload.issue.number);
  if (!issue.project) {
    core.debug(`skipping: issue/pr ${issue.number} is not associated with project ${FRAMEWORK_GROUP_PROJECT_NUMBER}`)
    return;
  }

  const projectInfo = await getProjectInfo(github, core, FRAMEWORK_GROUP_PROJECT_NUMBER)
  const inProgressOption = projectInfo.status_options.find(x => x.name == IN_PROGRESS_OPTION_NAME)

  if (!inProgressOption) {
    throw new Error(`Option "${IN_PROGRESS_OPTION_NAME}" not found`)
  }

  core.info(`get card for issue/pr ${issue.number}`)
  const cardId = (await addCard(github, core, projectInfo.node_id, issue.node_id)).node_id

  await setFieldValue(github, core, projectInfo.node_id, cardId, projectInfo.status_field_id, inProgressOption.id)
}