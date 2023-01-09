import * as marked from "npm:marked";

marked.setOptions({
    gfm: true,
    breaks: true,
});

async function fetchGithub(url: string, { headers = {}, method = "GET", body}: { headers?: Record<string, string>, body?: string } = {}) {
    const ghToken = Deno.env.get("GITHUB_TOKEN");
    headers['User-Agent']  ='Shopware Release Info Generator';

    if (ghToken) {
        headers["Authorization"] =  `token ${ghToken}`;
    }

    return fetch(url, {
        headers,
        method,
        body,
    });
}

async function generateVersionInfo() {
    const json = await (await fetchGithub("https://api.github.com/repos/shopware/platform/releases")).json();
    const vulnerabilities = await fetchVulnerabilities();

    for (const release of json) {
        if (json.prerelease || json.draft) {
            continue;
        }

        const detail = await (await fetchGithub(release.url)).json();

        const body = marked.parse(detail.body);
        Deno.writeTextFileSync(`${release.tag_name.substring(1)}.json`, JSON.stringify({
            title: release.name,
            body,
            date: release.published_at,
            version: release.tag_name.substring(1),
            fixedVulnerabilities: vulnerabilities[release.tag_name.substring(1)] || [],
        }));
    }
}

async function generateVersionListing() {
    let currentPage = 1
    const versions = [];

    while (true) {
        const releases = await(await fetchGithub("https://api.github.com/repos/shopware/platform/releases?per_page=100&page=" + currentPage)).json();

        for (const release of releases) {
            if (release.prerelease || release.draft) {
                continue;
            }

            versions.push(release.tag_name.substring(1));
        }

        if (releases.length !== 100) {
            break
        }

        currentPage++
    }

    Deno.writeTextFileSync(`index.json`, JSON.stringify(versions));
}

async function fetchVulnerabilities() {
    const json = await (await fetchGithub("https://api.github.com/graphql", {
        body: JSON.stringify({"query":"query { \n  securityVulnerabilities(package: \"shopware/platform\", first: 100) {\n    nodes {\n      severity\n      advisory {\n        summary\n        permalink\n      }\n      firstPatchedVersion {\n        identifier\n      }\n    }\n  }\n}","variables":{}}),
        headers: {
            "Content-Type": "application/json",
        },
        method: "POST",
    })).json();

    const formatted = {};

    for (const vulnerability of json.data.securityVulnerabilities.nodes) {
        if (formatted[vulnerability.firstPatchedVersion.identifier] === undefined) {
            formatted[vulnerability.firstPatchedVersion.identifier] = [];
        }

        formatted[vulnerability.firstPatchedVersion.identifier].push({
            severity: vulnerability.severity,
            summary: vulnerability.advisory.summary,
            link: vulnerability.advisory.permalink,
        });
    }

    return formatted;
}

generateVersionListing();
generateVersionInfo();
