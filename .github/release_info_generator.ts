import { marked } from "npm:marked";
import { baseUrl } from "npm:marked-base-url";

// Polyfill: the pinned Deno runtime's URL lacks the static canParse() that marked-base-url calls for every relative link.
if (typeof URL.canParse !== "function") {
    URL.canParse = (url: string, base?: string) => {
        try {
            new URL(url, base);
            return true;
        } catch {
            return false;
        }
    };
}

type Vulnerability = {
    severity: string,
    summary: string,
    link: string,
}

type GithubRelease = {
    name: string,
    url: string,
    tag_name: string,
    draft: boolean,
    published_at: string
}

type Release = {
    title: string,
    body: string,
    date: string,
    version: string,
    fixedVulnerabilities: Array<Vulnerability>,
}

type Severity = "critical" | "high" | "medium" | "low";

interface GithubVulnerability {
  patched_versions: string;
}

interface GithubSecurityAdvisory {
  html_url: string;
  summary: string;
  severity: Severity;
  vulnerabilities: GithubVulnerability[];
}

type FormattedVulnerabilities = Record<string, Vulnerability[]>;

type CveRecord = {
  dataType: string;
  cveMetadata: CveMetadata;
  containers: CveContainers;
};

type CveMetadata = {
  cveId: string;
};

type CveContainers = {
  cna: CveCna;
  adp?: CveAdp[];
};

type CveCna = {
  descriptions: CveDescription[];
};

type CveDescription = {
  value: string;
};

type CveAdp = {
  metrics?: CveAdpMetrics[];
};

type CveAdpMetrics = {
  cvssV3_1?: CveCvssV31;
};

type CveCvssV31 = {
  baseSeverity: "CRITICAL" | "HIGH" | "MEDIUM" | "LOW" | "NONE";
};

marked.setOptions({
    gfm: true,
    breaks: true,
});

function fetchGithub(url: string, { headers = {}, method = "GET", body }: { headers?: Record<string, string>, body?: string, method?: string } = {}) {
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

async function fetchMitreCve(cveID: string): Promise<CveRecord> {
    const res = await fetch(`https://cveawg.mitre.org/api/cve/${cveID}`, {
        method: "GET",
        headers: { "Content-Type": "application/json" },
      });

      if (!res.ok) {
        throw new Error(`CVE lookup failed (${cveID}): ${res.status} ${res.statusText}`);
      }

      return (await res.json()) as CveRecord;
}

async function fetchVulnerabilitiesByDescription(list: Array<Vulnerability>, body: string): Promise<Array<Vulnerability>> {
    const ghsaRegex = /GHSA-\w{4}-\w{4}-\w{4}/mg

    const matches = body.match(ghsaRegex);

    if (matches === null || matches.length === 0) {
        return list;
    }

    const unique = matches.filter((value, index, array) => array.indexOf(value) === index);
    const vulns = Array<Vulnerability>();

    for (const match of unique) {
        const res = await fetchGithub(`https://api.github.com/repos/shopware/shopware/security-advisories/${match}`);
        if (!res.ok) {
            if (res.status == 404) {
                continue;
            }
            throw new Error(`GHSA lookup failed (${match}): ${res.status} ${res.statusText}`);
        }
        const json = (await res.json()) as GithubSecurityAdvisory;

        if (json.severity === undefined) {
            continue;
        }

        vulns.push({
            severity: json.severity,
            summary: json.summary,
            link: json.html_url,
        } as Vulnerability);
    }

    return vulns
}

async function fetchCveVulnerabilitiesByDescription(list: Array<Vulnerability>, body: string) {
    const cveRegex = /CVE-\d{4}-\d{4,7}/mg

    const cveMatches = body.match(cveRegex);
    const cveList = cveMatches || [];
    const cveUnique = cveList.filter((value, index, array) => array.indexOf(value) === index);

    for (const match of cveUnique) {
        const cve = await fetchMitreCve(match);

        if (cve === undefined || cve.dataType !== "CVE_RECORD") {
            continue;
        }

        const vuln = parseMitreCve(cve);

        if (vuln === undefined) {
            continue;
        }

        list.push(vuln);
    }

    return list;
}

async function fetchAllReleases(): Promise<Array<GithubRelease>> {
    let currentPage = 1
    const latestReleaseResponse = await fetchGithub("https://api.github.com/repos/shopware/shopware/releases/latest");
    if (!latestReleaseResponse.ok) {
        throw new Error(`Failed to get latest release: ${latestReleaseResponse.status} ${latestReleaseResponse.statusText}`);
    }
    const latestRelease = (await latestReleaseResponse.json()) as GithubRelease;
    const versions = [];

    while (true) {
        const res = await fetchGithub("https://api.github.com/repos/shopware/shopware/releases?per_page=100&page=" + currentPage);
        if (!res.ok) {
            throw new Error(`Failed to get releases (page: ${currentPage}): ${res.status} ${res.statusText}`);
        }
        const releases = (await res.json()) as GithubRelease[];

        for (const release of releases) {
            if (release.draft) {
                continue;
            }

            if (release.tag_name === latestRelease.tag_name) {
                continue;
            }

            versions.push(release);
        }

        if (releases.length !== 100) {
            break
        }

        currentPage++
    }

    // put the release marked as latest always to the top
    return [latestRelease, ...versions]
}

function parseMitreCve(cve: CveRecord): Vulnerability | undefined {
    const severity = cve.containers.adp?.[0]?.metrics?.[0]?.cvssV3_1?.baseSeverity;
    const summary = cve.containers.cna.descriptions[0]?.value;
    const cveID = cve.cveMetadata.cveId;

    if (severity === undefined || summary === undefined) {
        return undefined;
    }

    const link = `https://www.cve.org/CVERecord?id=${cveID}`;

    return {
        severity: severity,
        summary: summary,
        link: link,
    } as Vulnerability;
}

async function generateVersionInfo(release: GithubRelease, vulnerabilities: FormattedVulnerabilities): Promise<Release | null> {
    if (release.draft) {
        return null;
    }

    marked.use(baseUrl(`https://github.com/shopware/shopware/blob/${release.tag_name}/changelog`));

    const detail = await (await fetchGithub(release.url)).json();

    const body = await marked.parse(detail.body);

    const ghsaVulns = await fetchVulnerabilitiesByDescription(vulnerabilities[release.tag_name.substring(1)] || [], body);
    const cveVulns = await fetchCveVulnerabilitiesByDescription(vulnerabilities[release.tag_name.substring(1)] || [], body);

    const formattedRelease: Release = {
        title: release.name,
        body,
        date: release.published_at,
        version: release.tag_name.substring(1),
        fixedVulnerabilities: [...ghsaVulns, ...cveVulns].filter((v, i, arr) => arr.findIndex(x => x.link === v.link) === i),
    };

    Deno.writeTextFileSync(`${release.tag_name.substring(1)}.json`, JSON.stringify(formattedRelease));

    return formattedRelease;
}

async function generateVersionListing() {
    const allReleases = await fetchAllReleases();
    const allVulnerabilities = await fetchVulnerabilities();

    const securityReleases = Array<string>();
    const releaseOverview = Array<string>();

    for (const release of allReleases) {
        releaseOverview.push(release.tag_name.replace(/^v/, ''));
        const releaseInfo = await generateVersionInfo(release, allVulnerabilities);
        if (releaseInfo == null) {
            console.log(`Failed to generate Release Info for ${release.tag_name}`);
            continue;
        }

        if (releaseInfo.fixedVulnerabilities.length > 0) {
            securityReleases.push(releaseInfo.version);
        }
    }

    Deno.writeTextFileSync(`index.json`, JSON.stringify(releaseOverview));
    Deno.writeTextFileSync('security_releases.json', JSON.stringify(securityReleases));
}

async function fetchVulnerabilities(): Promise<FormattedVulnerabilities> {
    const response = await fetchGithub("https://api.github.com/repos/shopware/shopware/security-advisories?per_page=100&state=published");
    const json = (await response.json()) as GithubSecurityAdvisory[];

    const formatted: FormattedVulnerabilities = {};

    for (const advisory of json) {
        const firstVulnerability = advisory.vulnerabilities[0];
        if (!firstVulnerability) continue;

        const firstPatchedVersion = firstVulnerability.patched_versions.split(",").pop();
        if (!firstPatchedVersion) continue;

        if (formatted[firstPatchedVersion] === undefined) {
            formatted[firstPatchedVersion] = [];
        }

        formatted[firstPatchedVersion].push({
            severity: advisory.severity,
            summary: advisory.summary,
            link: advisory.html_url,
        });
    }

    return formatted;
}

if (Deno.env.get("GITHUB_TOKEN") === undefined) {
    throw Error("GITHUB_TOKEN needs to be set");
}

await generateVersionListing();
