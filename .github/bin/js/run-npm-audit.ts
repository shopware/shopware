import { execSync } from 'node:child_process';

export interface NpmAuditOptions {
    ignoredCVEs?: string[];
    ignoredGHSAs?: string[];
}

interface AuditVia {
    source: number;
    name: string;
    title: string;
    url: string;
    severity: string;
    range: string;
    cwe: string[];
    cvss: { score: number; vectorString: string };
}

interface AuditVulnerability {
    name: string;
    severity: string;
    range: string;
    via: Array<AuditVia | string>;
    isDirect: boolean;
    effects: string[];
    fixAvailable:
        | boolean
        | {
              name: string;
              version: string;
              isSemVerMajor: boolean;
          };
}

interface AuditResult {
    vulnerabilities: Record<string, AuditVulnerability>;
}

interface RootAdvisory {
    ghsa: string | null;
    title: string;
    severity: string;
    url: string;
    packageName: string;
    range: string;
    affectedPackages: string[];
    fixAvailable:
        | false
        | {
              name: string;
              version: string;
              isSemVerMajor: boolean;
          };
}

function extractGHSA(url: string): string | null {
    const match = url.match(/(GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4})/);
    return match?.[1] ?? null;
}

function extractCVEs(via: AuditVia): string[] {
    const raw = via as unknown as Record<string, unknown>;
    if (Array.isArray(raw['cve'])) return raw['cve'] as string[];
    if (typeof raw['cve'] === 'string') return [raw['cve'] as string];
    return [];
}

function fetchAuditReport(): AuditResult {
    let auditRaw = '';

    try {
        auditRaw = execSync('npm audit --json', { encoding: 'utf8' });
    } catch (err: unknown) {
        const execErr = err as { stdout?: Buffer | string; message?: string };
        if (execErr.stdout) {
            auditRaw = execErr.stdout.toString();
        } else {
            console.error('Error running npm audit:', execErr.message ?? String(err));
            process.exit(1);
        }
    }

    try {
        return JSON.parse(auditRaw) as AuditResult;
    } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        console.error('Failed to parse npm audit JSON:', message);
        process.exit(1);
    }
}

function isIgnored(via: AuditVia, ignoredGHSAs: Set<string>, ignoredCVEs: Set<string>): boolean {
    const ghsa = via.url ? extractGHSA(via.url) : null;
    if (ghsa && ignoredGHSAs.has(ghsa)) {
        return true;
    }

    if (ignoredCVEs.size > 0) {
        for (const cve of extractCVEs(via)) {
            if (ignoredCVEs.has(cve)) {
                return true;
            }
        }
    }

    return false;
}

function filterIgnored(audit: AuditResult, ignoredGHSAs: Set<string>, ignoredCVEs: Set<string>): void {
    for (const pkgName in audit.vulnerabilities) {
        const pkg = audit.vulnerabilities[pkgName];
        if (!pkg) continue;

        if (Array.isArray(pkg.via)) {
            pkg.via = pkg.via.filter((v) => {
                if (typeof v === 'object') {
                    return !isIgnored(v, ignoredGHSAs, ignoredCVEs);
                }
                return true;
            });
        }
    }

    let changed = true;
    while (changed) {
        changed = false;
        for (const pkgName in audit.vulnerabilities) {
            const pkg = audit.vulnerabilities[pkgName];
            if (!pkg) continue;

            if (Array.isArray(pkg.via) && pkg.via.length > 0) {
                pkg.via = pkg.via.filter((v) => {
                    if (typeof v === 'string') {
                        const refPkg = audit.vulnerabilities[v];
                        return refPkg && Array.isArray(refPkg.via) && refPkg.via.length > 0;
                    }
                    return true;
                });
                if (pkg.via.length === 0) {
                    changed = true;
                }
            }
        }
    }
}

function collectAffectedPackages(
    pkgName: string,
    audit: AuditResult,
    visited: Set<string>,
): void {
    if (visited.has(pkgName)) return;
    visited.add(pkgName);

    for (const otherName in audit.vulnerabilities) {
        const other = audit.vulnerabilities[otherName];
        if (!other) continue;

        if (Array.isArray(other.via)) {
            const dependsOnPkg = other.via.some((v) => typeof v === 'string' && v === pkgName);
            if (dependsOnPkg) {
                collectAffectedPackages(otherName, audit, visited);
            }
        }
    }
}

function buildRootAdvisories(audit: AuditResult): RootAdvisory[] {
    const advisoryMap = new Map<string, RootAdvisory>();

    for (const pkgName in audit.vulnerabilities) {
        const pkg = audit.vulnerabilities[pkgName];
        if (!pkg || !Array.isArray(pkg.via)) continue;

        for (const v of pkg.via) {
            if (typeof v !== 'object') continue;

            const ghsa = v.url ? extractGHSA(v.url) : null;
            const key = ghsa ?? `source-${v.source}`;

            if (!advisoryMap.has(key)) {
                const affected = new Set<string>();
                collectAffectedPackages(pkgName, audit, affected);
                affected.delete(pkgName);

                const fix = pkg.fixAvailable;
                advisoryMap.set(key, {
                    ghsa,
                    title: v.title || 'Unknown vulnerability',
                    severity: v.severity || pkg.severity || 'unknown',
                    url: v.url || '',
                    packageName: v.name || pkgName,
                    range: v.range || pkg.range || 'N/A',
                    affectedPackages: [...affected].sort(),
                    fixAvailable: fix && typeof fix === 'object' ? fix : false,
                });
            }
        }
    }

    return [...advisoryMap.values()];
}

function printAdvisories(advisories: RootAdvisory[], totalPackages: number): void {
    const plural = advisories.length === 1 ? 'advisory' : 'advisories';
    console.error(`Found ${advisories.length} ${plural} affecting ${totalPackages} package(s)\n`);

    for (const adv of advisories) {
        const identifiers: string[] = [];
        if (adv.ghsa) identifiers.push(adv.ghsa);
        const idStr = identifiers.length > 0 ? ` (${identifiers.join(', ')})` : '';

        console.error(`${adv.title}${idStr}`);
        console.error(`  Package: ${adv.packageName} ${adv.range}`);
        console.error(`  Severity: ${adv.severity}`);
        if (adv.url) {
            console.error(`  URL: ${adv.url}`);
        }
        if (adv.fixAvailable) {
            console.error(`  Fix: update ${adv.fixAvailable.name} to ${adv.fixAvailable.version}${adv.fixAvailable.isSemVerMajor ? ' (semver major)' : ''}`);
        }
        if (adv.affectedPackages.length > 0) {
            console.error(`  Affected: ${adv.affectedPackages.join(', ')} (${adv.affectedPackages.length} transitive)`);
        }
        console.error('');
    }
}

function printSuggestions(advisories: RootAdvisory[]): void {
    console.error('--- Suggestions ---\n');

    const fixable = advisories.filter((a) => a.fixAvailable);
    const unfixable = advisories.filter((a) => !a.fixAvailable);

    if (fixable.length > 0) {
        console.error('Try adding an override in package.json to fix the root dependency:\n');
        const seen = new Set<string>();
        for (const adv of fixable) {
            const key = `${adv.fixAvailable && typeof adv.fixAvailable === 'object' ? adv.fixAvailable.name : ''}`;
            if (seen.has(key)) continue;
            seen.add(key);
            const fix = adv.fixAvailable as { name: string; version: string; isSemVerMajor: boolean };
            console.error(`  "overrides": { "${fix.name}": "${fix.version}" }${fix.isSemVerMajor ? '  // semver major — test thoroughly' : ''}`);
        }
        console.error('');
    }

    if (unfixable.length > 0) {
        console.error('If the vulnerability does not affect your project (e.g. devDep only,');
        console.error('false positive), add to ignoredGHSAs in scripts/runNpmAudit.ts:\n');
        for (const adv of unfixable) {
            if (adv.ghsa) {
                const url = `https://github.com/advisories/${adv.ghsa}`;
                console.error(`  '${url}', // ${adv.packageName} ${adv.title}, ${adv.severity} severity`);
            } else {
                console.error(`  // ${adv.packageName}: ${adv.title} — no GHSA found, check ${adv.url}`);
            }
        }
        console.error('');
    }

    if (fixable.length > 0 && unfixable.length === 0) {
        console.error('All vulnerabilities have fixes available. Prefer overrides over ignoring.\n');
    }
}

/**
 * Run npm audit in the current working directory, filtering out advisories
 * matching the given GHSA and CVE identifiers.
 * Exits with code 1 when unignored vulnerabilities remain.
 */
export function runNpmAudit(options: NpmAuditOptions = {}): void {
    const ignoredGHSAs = new Set(
        (options.ignoredGHSAs ?? []).map((entry) => extractGHSA(entry) ?? entry),
    );
    const ignoredCVEs = new Set(options.ignoredCVEs ?? []);
    const totalIgnored = ignoredGHSAs.size + ignoredCVEs.size;

    const audit = fetchAuditReport();
    filterIgnored(audit, ignoredGHSAs, ignoredCVEs);

    const remaining = Object.values(audit.vulnerabilities).filter(
        (pkg) => Array.isArray(pkg.via) && pkg.via.length > 0,
    );

    if (remaining.length === 0) {
        console.log(`No vulnerabilities (${totalIgnored} ignored).`);
        return;
    }

    const advisories = buildRootAdvisories(audit);
    printAdvisories(advisories, remaining.length);
    printSuggestions(advisories);
    process.exit(1);
}
