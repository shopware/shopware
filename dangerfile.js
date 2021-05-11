const GitlabClient = require('gitlab');

const api = new GitlabClient.Gitlab({
    token: process.env['DANGER_GITLAB_API_TOKEN'],
    host: process.env['DANGER_GITLAB_HOST']
});

const addLabel = (labels) => {
    const currentLabels = danger.gitlab.mr.labels;

    for (const label of labels) {
        currentLabels.push(label);
    }

    api.MergeRequests.edit(1, process.env['CI_MERGE_REQUEST_IID'], {
        'labels': currentLabels.join(',')
    })
};

const removeLabel = (labels) => {
    const currentLabels = danger.gitlab.mr.labels;

    for (const label of labels) {
        const index = currentLabels.indexOf(label);
        if (index > -1) {
            currentLabels.splice(index, 1);
        }

        api.MergeRequests.edit(1, process.env['CI_MERGE_REQUEST_IID'], {
            'labels': currentLabels.join(',')
        })
    }
};

const hasStoreApiRouteChanges = () => {
    for (let file of danger.git.modified_files) {
        if (file.includes('SalesChannel') && file.includes('Route.php') && !file.includes('/Test/')) {
            return true;
        }
    }

    for (let file of danger.git.created_files) {
        if (file.includes('SalesChannel') && file.includes('Route.php') && !file.includes('/Test/')) {
            return true;
        }
    }

    return false;
}

if (hasStoreApiRouteChanges()) {
    warn('Store-API Route has been modified. @Reviewers please review carefully!')
    addLabel(['Security-Audit Required']);
}
