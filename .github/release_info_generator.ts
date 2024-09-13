import { marked } from "npm:marked";
import { baseUrl } from "npm:marked-base-url";

type Vulnerability = {
    severity: string,
    summary: string,
    link: string,
}

marked.setOptions({
    gfm: true,
    breaks: true,
});

async function fetchGithub(url: string, { headers = {}, method = "GET", body }: { headers?: Record<string, string>, body?: string, method?: string } = {}) {
    const ghToken = Deno.env.get("GITHUB_TOKEN");
    headers['User-Agent'] = 'Shopware Release Info Generator';

    if (ghToken) {
        headers["Authorization"] = `token ${ghToken}`;
    }

    return fetch(url, {
        headers,
        method,
        body,
    });
}

async function fetchMitreCve(cveID: string) {
    return fetch(`https://cveawg.mitre.org/api/cve/${cveID}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    })
}

async function fetchVulnerabilitiesByDescription(list: Array<Vulnerability>, body: string) {
    const ghsaRegex = /GHSA-\w{4}-\w{4}-\w{4}/mg

    const matches = body.match(ghsaRegex);

    if (matches === null || matches.length === 0) {
        return list;
    }

    const unique = matches.filter((value, index, array) => array.indexOf(value) === index);

    for (let match of unique) {
        const json = await (await fetchGithub(`https://api.github.com/repos/shopware/shopware/security-advisories/${match}`)).json();

        if (json.severity === undefined) {
            continue;
        }

        list.push({
            severity: json.severity,
            summary: json.summary,
            link: json.html_url,
        } as Vulnerability);
    }

    return list
}

async function fetchCveVulnerabilitiesByDescription(list: Array<Vulnerability>, body: string) {
    const cveRegex = /CVE-\d{4}-\d{4,7}/mg

    const cveMatches = body.match(cveRegex);
    const cveList = cveMatches || [];
    const cveUnique = cveList.filter((value, index, array) => array.indexOf(value) === index);

    for (let match of cveUnique) {
        const response = await fetchMitreCve(match);
        const json = await response.json();

        if (json === undefined || json.dataType !== "CVE_RECORD") {
            continue;
        }

        const vuln = parseMitreCve(json);

        if (vuln === undefined) {
            continue;
        }

        list.push(vuln);
    }

    return list;
}

function parseMitreCve(cve: any) {
    const severity = cve?.containers?.cna?.metrics[0]?.cvssV3_1?.baseSeverity;
    const summary = cve?.containers?.cna?.title;
    const cveID = cve?.cveMetadata?.cveId;

    if (severity === undefined || summary === undefined || cveID === undefined) {
        return undefined;
    }

    const link = `https://www.cve.org/CVERecord?id=${cveID}`;

    return {
        severity: severity,
        summary: summary,
        link: link,
    } as Vulnerability;
}

async function generateVersionInfo() {
    const json = await (await fetchGithub("https://api.github.com/repos/shopware/shopware/releases")).json();
    const vulnerabilities = await fetchVulnerabilities();

    for (const release of json) {
        if (json.draft) {
            continue;
        }

        marked.use(baseUrl(`https://github.com/shopware/shopware/blob/${release.tag_name}/changelog`));

        const detail = await (await fetchGithub(release.url)).json();

        const body = marked.parse(detail.body);

        const ghsaVulns = await fetchVulnerabilitiesByDescription(vulnerabilities[release.tag_name.substring(1)] || [], body);
        const cveVulns = await fetchCveVulnerabilitiesByDescription(vulnerabilities[release.tag_name.substring(1)] || [], body);

        Deno.writeTextFileSync(`${release.tag_name.substring(1)}.json`, JSON.stringify({
            title: release.name,
            body,
            date: release.published_at,
            version: release.tag_name.substring(1),
            fixedVulnerabilities: [...ghsaVulns, ...cveVulns],
        }));
    }
}

async function generateVersionListing() {
    let currentPage = 1
    const latestRelease = await (await fetchGithub("https://api.github.com/repos/shopware/shopware/releases/latest")).json();
    const versions = [];

    while (true) {
        const releases = await (await fetchGithub("https://api.github.com/repos/shopware/shopware/releases?per_page=100&page=" + currentPage)).json();

        for (const release of releases) {
            if (release.draft) {
                continue;
            }

            if (release.tag_name === latestRelease.tag_name) {
                continue;
            }

            versions.push(release.tag_name.substring(1));
        }

        if (releases.length !== 100) {
            break
        }

        currentPage++
    }

    // put the release marked as latest always to the top
    const allVersions = [latestRelease.tag_name.substring(1), ...versions]

    Deno.writeTextFileSync(`index.json`, JSON.stringify(allVersions));
}

async function fetchVulnerabilities() {
    const json = await (await fetchGithub("https://api.github.com/repos/shopware/shopware/security-advisories?per_page=100&state=published")).json();

    const formatted = {};

    for (const vulnerability of json) {
        const firstPatchedVersion = vulnerability.vulnerabilities[0].patched_versions.split(',').pop();

        if (formatted[firstPatchedVersion] === undefined) {
            formatted[firstPatchedVersion] = [];
        }

        formatted[firstPatchedVersion].push({
            severity: vulnerability.severity,
            summary: vulnerability.summary,
            link: vulnerability.html_url,
        });
    }

    return formatted;
}

await generateVersionListing();
await generateVersionInfo();
