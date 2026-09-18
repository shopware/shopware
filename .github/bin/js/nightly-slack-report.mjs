import { appendFileSync } from "node:fs";

const githubToken = requiredEnv("GITHUB_TOKEN");
const repository = requiredEnv("GITHUB_REPOSITORY");
const runId = requiredEnv("GITHUB_RUN_ID");
const runAttempt = requiredEnv("GITHUB_RUN_ATTEMPT");
const serverUrl = process.env.GITHUB_SERVER_URL || "https://github.com";
const apiUrl =
    process.env.GITHUB_API_URL ||
    (serverUrl === "https://github.com"
        ? "https://api.github.com"
        : `${serverUrl}/api/v3`);
const refName = process.env.GITHUB_REF_NAME || "unknown ref";
const eventName = process.env.GITHUB_EVENT_NAME || "unknown event";
const currentJobName = process.env.CURRENT_JOB_NAME || "Nightly health report";
const slackWorkflowUrl = process.env.SLACK_WORKFLOW_URL || "";

const workflowOrder = [
    "nightly",
    "admin",
    "integration",
    "visual-tests",
    "php",
    "storefront",
    "downstream",
    "prepare-release",
];

const workflowNames = new Map([
    ["nightly", "Nightly"],
    ["admin", "Admin checks and tests"],
    ["integration", "Integration tests"],
    ["visual-tests", "Visual Tests"],
    ["php", "PHP checks"],
    ["storefront", "Storefront checks and tests"],
    ["downstream", "Downstream"],
    ["prepare-release", "Prepare release"],
]);

const jobs = await fetchCompletedJobs();
const groupedJobs = groupJobs(jobs);
const report = buildReport(groupedJobs);

console.log(report);
writeStepSummary(report);

if (!slackWorkflowUrl) {
    console.log(
        "SLACK_WORKFLOW_URL is not configured, skipping Slack notification.",
    );
} else {
    await postToSlack(report);
}

function requiredEnv(name) {
    const value = process.env[name];

    if (!value) {
        throw new Error(`${name} is required`);
    }

    return value;
}

async function fetchCompletedJobs() {
    const [owner, repo] = repository.split("/");

    if (!owner || !repo) {
        throw new Error(`Invalid GITHUB_REPOSITORY value: ${repository}`);
    }

    const perPage = 100;
    const allJobs = [];

    for (let page = 1; ; page += 1) {
        const url = new URL(
            `${apiUrl}/repos/${owner}/${repo}/actions/runs/${runId}/attempts/${runAttempt}/jobs`,
        );
        url.searchParams.set("per_page", String(perPage));
        url.searchParams.set("page", String(page));

        const response = await fetch(url, {
            headers: {
                Accept: "application/vnd.github+json",
                Authorization: `Bearer ${githubToken}`,
                "X-GitHub-Api-Version": "2022-11-28",
            },
        });

        if (!response.ok) {
            throw new Error(
                `GitHub API request failed with ${response.status}: ${await response.text()}`,
            );
        }

        const body = await response.json();
        const pageJobs = body.jobs || [];
        allJobs.push(...pageJobs);

        if (pageJobs.length < perPage) {
            break;
        }
    }

    return allJobs.filter(
        (job) => job.status === "completed" && job.name !== currentJobName,
    );
}

function groupJobs(jobs) {
    const groups = new Map();

    for (const job of jobs) {
        const { workflowKey, jobName } = parseJobName(job.name);

        if (!groups.has(workflowKey)) {
            groups.set(workflowKey, []);
        }

        groups.get(workflowKey).push({ ...job, displayName: jobName });
    }

    return [...groups.entries()]
        .sort(([left], [right]) => compareWorkflowKeys(left, right))
        .map(([workflowKey, workflowJobs]) => ({
            workflowKey,
            workflowName:
                workflowNames.get(workflowKey) || titleCase(workflowKey),
            jobs: workflowJobs.sort(compareJobs),
        }));
}

function parseJobName(name) {
    const separator = " / ";
    const separatorIndex = name.indexOf(separator);

    if (separatorIndex === -1) {
        return { workflowKey: "nightly", jobName: name };
    }

    return {
        workflowKey: name.slice(0, separatorIndex),
        jobName: name.slice(separatorIndex + separator.length),
    };
}

function compareWorkflowKeys(left, right) {
    const leftIndex = workflowOrder.indexOf(left);
    const rightIndex = workflowOrder.indexOf(right);

    if (leftIndex !== -1 || rightIndex !== -1) {
        return normalizeOrderIndex(leftIndex) - normalizeOrderIndex(rightIndex);
    }

    return left.localeCompare(right);
}

function normalizeOrderIndex(index) {
    return index === -1 ? Number.MAX_SAFE_INTEGER : index;
}

function compareJobs(left, right) {
    const conclusionDiff =
        conclusionRank(left.conclusion) - conclusionRank(right.conclusion);

    if (conclusionDiff !== 0) {
        return conclusionDiff;
    }

    return left.displayName.localeCompare(right.displayName);
}

function conclusionRank(conclusion) {
    if (isFailureLike(conclusion)) {
        return 0;
    }

    if (conclusion === "success") {
        return 1;
    }

    if (conclusion === "skipped") {
        return 2;
    }

    return 3;
}

function isFailureLike(conclusion) {
    return conclusion && conclusion !== "success" && conclusion !== "skipped";
}

function buildReport(groups) {
    const allJobs = groups.flatMap((group) => group.jobs);
    const failedJobs = allJobs.filter((job) => isFailureLike(job.conclusion));
    const successfulJobs = allJobs.filter(
        (job) => job.conclusion === "success",
    );
    const skippedJobs = allJobs.filter((job) => job.conclusion === "skipped");
    const runUrl = `${serverUrl}/${repository}/actions/runs/${runId}/attempts/${runAttempt}`;

    const lines = [
        "*Platform Nightly health summary*",
        `Run: ${escapeSlackText(repository)} - ${runUrl}`,
        `Result: failed ${failedJobs.length}, successful ${successfulJobs.length}, skipped ${skippedJobs.length}`,
        "",
    ];

    for (const group of groups) {
        const failedInGroup = group.jobs.filter((job) =>
            isFailureLike(job.conclusion),
        ).length;
        lines.push(
            `*${escapeSlackText(group.workflowName)}* (${group.jobs.length} jobs, failed ${failedInGroup})`,
        );

        for (const job of group.jobs) {
            lines.push(formatJobLine(job));
        }

        lines.push("");
    }

    return lines.join("\n").trim();
}

function formatJobLine(job) {
    const conclusion = job.conclusion || "unknown";
    const displayName = escapeSlackText(job.displayName);
    const icon = conclusionIcon(conclusion);

    if (isFailureLike(conclusion)) {
        return `- ${icon} ${displayName} ( ${job.html_url} )`;
    }

    return `- ${icon} ${displayName}`;
}

function conclusionIcon(conclusion) {
    if (conclusion === "success") {
        return ":white_check_mark:";
    }

    if (conclusion === "skipped") {
        return ":heavy_minus_sign:";
    }

    if (isFailureLike(conclusion)) {
        return ":x:";
    }

    return ":grey_question:";
}

function escapeSlackText(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\|/g, "/");
}

function titleCase(value) {
    return String(value)
        .split("-")
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function writeStepSummary(report) {
    if (!process.env.GITHUB_STEP_SUMMARY) {
        return;
    }

    const markdown = report
        .replace(/^\*([^*]+)\*/gm, "## $1")
        .replace(/<([^|>]+)\|([^>]+)>/g, "[$2]($1)");

    appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${markdown}\n`);
}

async function postToSlack(report) {
    const response = await fetch(slackWorkflowUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ message: report }),
    });

    if (!response.ok) {
        throw new Error(
            `Slack workflow request failed with ${response.status}: ${await response.text()}`,
        );
    }
}
