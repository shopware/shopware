#!/usr/bin/env node

// Aggregates the junit.xml artifacts of a failed nightly PHPUnit run into a
// tracking-issue payload (consumed by .github/workflows/report-phpunit-failures.yml).
// Failing tests are grouped by owning domain, resolved from the test file's
// #[Package] attribute with a fallback to the dominant package of the mirrored
// src/ directory. This is an inventory, not a triage: clustering failures into
// root causes and filing per-domain issues stays a manual step — see
// .agents/skills/nightly-triage/SKILL.md.
//
// A failed lane whose junit carries no failing test is reported as its own issue:
// the junit-phpunit-* artifacts only exist for failed jobs, so a clean report
// there means the failure is invisible to testcase-level aggregation (a
// runner-level PHPUnit error, or a non-test step failing after a green suite).

import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';

export interface FailedTest {
  className: string;
  testName: string;
  file: string;
  message: string;
}

interface DomainGroup {
  label: string;
  packageKeys: Set<string>;
  tests: FailedTest[];
}

interface IssueContent {
  issueTitle: string;
  issueMarker: string;
  issueBody: string;
  commentBody: string;
}

interface DomainIssue extends IssueContent {
  label: string | null;
}

interface IssuePayload {
  issues: DomainIssue[];
}

const MAX_TESTS_PER_DOMAIN = 40;
const UNROUTED_LABEL = 'needs manual routing';

// Package key → domain label. KEEP IN SYNC with the canonical catalogue in
// .agents/skills/sw-triage/references/DOMAINS.md (also mirrored by
// .github/bin/js/validate-sw-triage-output.ts). Keys that map to no label
// there (innovation, buyers-experience) intentionally stay unrouted here.
const PACKAGE_LABELS: Record<string, string> = {
  'framework': 'domain/framework',
  'fundamentals@framework': 'domain/framework',
  'framework:fundamentals': 'domain/framework',
  'checkout': 'domain/checkout',
  'discovery': 'domain/discovery',
  'fundamentals@discovery': 'domain/discovery',
  'inventory': 'domain/inventory',
  'after-sales': 'domain/crm-after-sales',
  'fundamentals@after-sales': 'domain/crm-after-sales',
  'data-services': 'service/data-intelligence',
};

const PACKAGE_ATTRIBUTE = /#\[Package\('([^']+)'\)\]|@sw-package\s+([\w@:-]+)/;

function decodeXmlEntities(value: string): string {
  return value
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&#(\d+);/g, (_, code: string) => String.fromCharCode(Number(code)))
    .replace(/&amp;/g, '&');
}

function parseAttributes(tag: string): Record<string, string> {
  const attributes: Record<string, string> = {};
  for (const match of tag.matchAll(/([\w-]+)="([^"]*)"/g)) {
    attributes[match[1]] = decodeXmlEntities(match[2]);
  }
  return attributes;
}

// PHPUnit prefixes the failure text with the "Class::test" identifier line;
// the first line after that is the actual message.
function firstMessageLine(failureText: string): string {
  const lines = failureText
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line !== '');
  const message = lines[0]?.includes('::') ? lines[1] : lines[0];

  if (!message) {
    return '';
  }

  return message.length > 200 ? `${message.slice(0, 200)}…` : message;
}

// junit.xml testcases report absolute runner paths; reduce to repo-relative.
function toRepoRelative(file: string): string {
  const match = file.match(/(?:^|\/)((?:tests|src)\/.*)$/);
  return match ? match[1] : file;
}

export function parseJUnitReport(xml: string): FailedTest[] {
  const failures: FailedTest[] = [];

  for (const testcase of xml.matchAll(/<testcase\b([^>]*?)(?:\/>|>([\s\S]*?)<\/testcase>)/g)) {
    const body = testcase[2];
    if (!body) {
      continue; // self-closing testcase: passed
    }

    const failure = body.match(/<(failure|error)\b[^>]*>([\s\S]*?)<\/\1>/);
    if (!failure) {
      continue; // skipped or warning-only testcase
    }

    const attributes = parseAttributes(testcase[1]);
    failures.push({
      className: attributes['class'] ?? attributes['classname'] ?? 'unknown',
      testName: attributes['name'] ?? 'unknown',
      file: toRepoRelative(attributes['file'] ?? ''),
      message: firstMessageLine(decodeXmlEntities(failure[2])),
    });
  }

  return failures;
}

/**
 * jest-junit reports carry no file attribute and duplicate the describe chain into both
 * classname and name; the testsuite name is the root describe title. The spec file is
 * recovered from the failure stack trace, falling back to the suite title when it is a
 * component path by convention.
 */
export function parseJestJUnitReport(xml: string, appRoot: string): FailedTest[] {
  const failures: FailedTest[] = [];

  for (const suite of xml.matchAll(/<testsuite\b([^>]*)>([\s\S]*?)<\/testsuite>/g)) {
    const suiteName = parseAttributes(suite[1])['name'] ?? 'unknown';
    // any stack trace in the suite names the spec file; a path-like suite title is the
    // component directory by convention — either carries the routing @sw-package marker
    const suiteSpecFile = suite[2].match(/[^\s():]+\.spec\.[jt]s/)?.[0];
    const suiteFile = suiteSpecFile
      ? toRepoRelative(suiteSpecFile)
      : (suiteName.startsWith('src/') ? `${appRoot}/${suiteName}` : '');

    for (const testcase of suite[2].matchAll(/<testcase\b([^>]*?)(?:\/>|>([\s\S]*?)<\/testcase>)/g)) {
      const body = testcase[2];
      const failure = body?.match(/<(failure|error)\b[^>]*>([\s\S]*?)<\/\1>/);
      if (!failure) {
        continue;
      }

      const attributes = parseAttributes(testcase[1]);
      const name = attributes['name'] ?? 'unknown';

      failures.push({
        className: suiteName,
        testName: name.startsWith(`${suiteName} `) ? name.slice(suiteName.length + 1) : name,
        file: suiteFile,
        message: firstMessageLine(decodeXmlEntities(failure[2])),
      });
    }
  }

  return failures;
}

function tryReadPackageKey(file: string): string | null {
  try {
    const match = readFileSync(file, 'utf8').match(PACKAGE_ATTRIBUTE);

    return match?.[1] ?? match?.[2] ?? null;
  } catch {
    return null;
  }
}

// Dominant package key among the PHP/JS/TS files of one directory (non-recursive).
function dominantPackageKey(directory: string): string | null {
  let entries;
  try {
    entries = readdirSync(directory, { withFileTypes: true });
  } catch {
    return null;
  }

  const counts = new Map<string, number>();
  for (const entry of entries) {
    if (!entry.isFile() || !/\.(php|js|ts)$/.test(entry.name)) {
      continue;
    }
    const key = tryReadPackageKey(join(directory, entry.name));
    if (key) {
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }
  }

  let dominant: string | null = null;
  let dominantCount = 0;
  for (const [key, count] of counts) {
    if (count > dominantCount) {
      dominant = key;
      dominantCount = count;
    }
  }

  return dominant;
}

export function resolvePackageKey(testFile: string, repoRoot: string): string | null {
  if (testFile === '') {
    return null;
  }

  // Jest failures reference their component directory instead of a file
  const stats = statSync(join(repoRoot, testFile), { throwIfNoEntry: false });
  if (stats?.isDirectory()) {
    return dominantPackageKey(join(repoRoot, testFile));
  }

  const ownKey = tryReadPackageKey(join(repoRoot, testFile));
  if (ownKey) {
    return ownKey;
  }

  // Jest specs sit next to their component sources
  if (/\.spec\.[jt]s$/.test(testFile)) {
    return dominantPackageKey(join(repoRoot, dirname(testFile)));
  }

  // tests/{integration,unit}/ mirrors src/, tests/migration/<Bundle>/ mirrors
  // src/<Bundle>/Migration/ — fall back to the mirrored directory.
  const mirrored = testFile
    .replace(/^tests\/(?:integration|unit)\//, 'src/')
    .replace(/^tests\/migration\/([^/]+)\//, 'src/$1/Migration/');
  if (mirrored === testFile) {
    return null;
  }

  return dominantPackageKey(join(repoRoot, dirname(mirrored)));
}

export function groupByDomain(tests: FailedTest[], repoRoot: string): DomainGroup[] {
  const groups = new Map<string, DomainGroup>();

  for (const test of tests) {
    const packageKey = resolvePackageKey(test.file, repoRoot);
    const label = (packageKey && PACKAGE_LABELS[packageKey]) ?? UNROUTED_LABEL;

    let group = groups.get(label);
    if (!group) {
      group = { label, packageKeys: new Set(), tests: [] };
      groups.set(label, group);
    }
    if (packageKey) {
      group.packageKeys.add(packageKey);
    }
    group.tests.push(test);
  }

  for (const group of groups.values()) {
    group.tests.sort((left, right) =>
      `${left.className}::${left.testName}`.localeCompare(`${right.className}::${right.testName}`)
    );
  }

  return [...groups.values()].sort((left, right) => right.tests.length - left.tests.length);
}

function formatTest(test: FailedTest): string {
  const shortClass = test.className.replace(/^Shopware\\Tests\\/, '');
  const message = test.message === '' ? '' : `: ${test.message}`;
  return `- \`${shortClass}::${test.testName}\`${message}`;
}

const TRIAGE_NOTE =
  'Grouping uses the test files\' `#[Package]`/`@sw-package` markers only. Root-cause clustering, routing overrides, ' +
  'and re-routing between the per-domain issues are the deep-triage pass, see `.agents/skills/nightly-triage/SKILL.md`.';

// A run can fail without producing any junit report (infrastructure failure,
// a shard dying before reporting). That still needs an issue — a run that
// reports nothing must not look like a run without failures.
function buildNoReportsIssue(issueTitle: string, runUrl: string): DomainIssue {
  const marker = '<!-- nightly-phpunit-failures:no-reports -->';
  const title = `${issueTitle}: no test reports`;
  const lines = [
    `Run: ${runUrl}`,
    '',
    'No junit reports were produced: the failure is outside the reported test suites (PHPUnit and Jest), or a job died before reporting. Check the run logs.',
  ];

  return {
    issueTitle: title,
    issueMarker: marker,
    label: null,
    issueBody: [marker, `# ${title}`, '', 'Latest failure:', '', ...lines].join('\n').trimEnd(),
    commentBody: [marker, '## Scheduled test failure update', '', ...lines].join('\n').trimEnd(),
  };
}

// The junit-phpunit-* artifacts are only uploaded by failed jobs; one without a
// failing testcase is a red lane the per-test aggregation cannot represent.
function buildSilentLanesIssue(issueTitle: string, silentLanes: string[], runUrl: string): DomainIssue {
  const marker = '<!-- nightly-phpunit-failures:failed-lane-without-test-failures -->';
  const title = `${issueTitle}: failed lane without test failures`;
  const lines = [
    `Run: ${runUrl}`,
    '',
    'These lanes failed, but their junit reports contain no failing test:',
    '',
    ...silentLanes.map((lane) => `- \`${lane}\``),
    '',
    'Either PHPUnit hit a runner-level error it cannot attribute to a test (the console summary counts an error, for example a crash during a setUpBeforeClass kernel boot, while the junit report stays clean), or a step after a green suite failed. The failure is only visible in the job logs.',
  ];

  return {
    issueTitle: title,
    issueMarker: marker,
    label: null,
    issueBody: [marker, `# ${title}`, '', 'Latest failure:', '', ...lines].join('\n').trimEnd(),
    commentBody: [marker, '## Scheduled test failure update', '', ...lines].join('\n').trimEnd(),
  };
}

function buildDomainLines(group: DomainGroup, runUrl: string): string[] {
  // the listing folds behind the count, so long updates stay scannable on the issue
  const lines = [`Run: ${runUrl}`, '<details>', `<summary>Failing tests: ${group.tests.length}</summary>`, ''];

  for (const test of group.tests.slice(0, MAX_TESTS_PER_DOMAIN)) {
    lines.push(formatTest(test));
  }
  if (group.tests.length > MAX_TESTS_PER_DOMAIN) {
    lines.push(`- …and ${group.tests.length - MAX_TESTS_PER_DOMAIN} more, see the run logs.`);
  }

  lines.push('', '</details>');

  return lines;
}

export function buildIssuePayload(
  issueTitle: string,
  groups: DomainGroup[],
  runUrl: string,
  silentLanes: string[] = []
): IssuePayload {
  const issues = groups.map((group): DomainIssue => {
    const slug = group.label === UNROUTED_LABEL ? 'needs-manual-routing' : group.label;
    const marker = `<!-- nightly-phpunit-failures:${slug} -->`;
    const title = `${issueTitle}: ${group.label}`;
    const lines = buildDomainLines(group, runUrl);

    return {
      issueTitle: title,
      issueMarker: marker,
      label: group.label === UNROUTED_LABEL ? null : group.label,
      issueBody: [
        marker,
        `# ${title}`,
        '',
        'Failing scheduled tests grouped to this domain, PHPUnit by its `#[Package]` markers, Jest by its `@sw-package` markers. New failures are added as comments.',
        '',
        TRIAGE_NOTE,
        '',
        'Latest failure:',
        '',
        ...lines,
      ]
        .join('\n')
        .trimEnd(),
      commentBody: [marker, '## Scheduled test failure update', '', ...lines].join('\n').trimEnd(),
    };
  });

  if (silentLanes.length > 0) {
    issues.push(buildSilentLanesIssue(issueTitle, silentLanes, runUrl));
  }

  if (issues.length === 0) {
    return { issues: [buildNoReportsIssue(issueTitle, runUrl)] };
  }

  return { issues };
}

function collectXmlFiles(directory: string): string[] {
  const entries = readdirSync(directory, { withFileTypes: true });

  return entries.flatMap((entry) => {
    const fullPath = join(directory, entry.name);

    if (entry.isDirectory()) {
      return collectXmlFiles(fullPath);
    }

    return entry.isFile() && entry.name.endsWith('.xml') ? [fullPath] : [];
  });
}

// The tracking issues only reflect trunk and the maintenance branches; a nightly
// dispatched manually on any other branch runs without reporting. The shapes mirror
// the branch globs of release-gate.yml (6.6.x minor lines, 6.7.11.x patch lines).
export function isNightlyBranch(refName: string): boolean {
  return refName === 'trunk' || /^\d+\.\d+(\.\d+)?\.x$/.test(refName);
}

/**
 * Walks the downloaded artifact directories. Every junit-phpunit-* artifact stems
 * from a failed job (their upload steps run on `if: failure()`), so an artifact
 * whose reports contain no failing testcase marks a red lane that testcase-level
 * aggregation cannot see.
 */
export function scanReports(reportDirectory: string): { failures: FailedTest[]; silentLanes: string[]; reports: number } {
  const seen = new Set<string>();
  const failures: FailedTest[] = [];
  const silentLanes: string[] = [];
  let reports = 0;

  const register = (xmlFile: string): number => {
    const parsed = parseReport(xmlFile);
    reports++;
    for (const failure of parsed) {
      const key = `${failure.className}::${failure.testName}`;
      if (!seen.has(key)) {
        seen.add(key);
        failures.push(failure);
      }
    }

    return parsed.length;
  };

  for (const entry of readdirSync(reportDirectory, { withFileTypes: true })) {
    const fullPath = join(reportDirectory, entry.name);

    if (entry.isFile()) {
      if (entry.name.endsWith('.xml')) {
        register(fullPath);
      }
      continue;
    }

    if (!entry.isDirectory()) {
      continue;
    }

    let laneFailures = 0;
    for (const xmlFile of collectXmlFiles(fullPath)) {
      laneFailures += register(xmlFile);
    }

    // an artifact directory without any junit.xml (a lane that died before
    // reporting) is silent too: collectXmlFiles finds nothing, laneFailures stays 0
    if (entry.name.startsWith('junit-phpunit-') && laneFailures === 0) {
      silentLanes.push(entry.name);
    }
  }

  silentLanes.sort();

  return { failures, silentLanes, reports };
}

function main(): void {
  const reportDirectory = process.argv[2];
  const payloadFile = process.argv[3];
  const issueTitle = process.env['REPORT_TITLE'];

  if (!reportDirectory || !payloadFile || !issueTitle) {
    console.error(
      'Usage: REPORT_TITLE="..." node report-phpunit-nightly-failures.ts <junit-report-directory> <payload-file>'
    );
    process.exit(1);
  }

  const refName = process.env['GITHUB_REF_NAME'] ?? '';
  if (!isNightlyBranch(refName)) {
    writeFileSync(payloadFile, `${JSON.stringify({ issues: [] })}\n`, 'utf8');
    console.log(`Branch '${refName}' is neither trunk nor a maintenance branch: reporting skipped.`);
    return;
  }

  const repoRoot = process.env['GITHUB_WORKSPACE'] ?? process.cwd();
  const runUrl =
    process.env['GITHUB_SERVER_URL'] && process.env['GITHUB_REPOSITORY'] && process.env['GITHUB_RUN_ID']
      ? `${process.env['GITHUB_SERVER_URL']}/${process.env['GITHUB_REPOSITORY']}/actions/runs/${process.env['GITHUB_RUN_ID']}`
      : 'GitHub Actions run URL unavailable';

  const stats = statSync(reportDirectory, { throwIfNoEntry: false });
  const scan = stats?.isDirectory()
    ? scanReports(resolve(reportDirectory))
    : { failures: [], silentLanes: [], reports: 0 };

  const payload = buildIssuePayload(issueTitle, groupByDomain(scan.failures, repoRoot), runUrl, scan.silentLanes);

  writeFileSync(payloadFile, `${JSON.stringify(payload)}\n`, 'utf8');
  console.log(
    `${scan.failures.length} failing tests aggregated from ${scan.reports} junit reports; ` +
      `${scan.silentLanes.length} failed lanes without test failures.`
  );
}

/** Jest artifacts land in jest-* subdirectories (see report-phpunit-failures.yml). */
export function jestAppRoot(xmlFile: string): string | null {
  const segment = xmlFile.split('/').find((part) => part.startsWith('jest-'));
  if (!segment) {
    return null;
  }

  return segment.includes('storefront')
    ? 'src/Storefront/Resources/app/storefront'
    : 'src/Administration/Resources/app/administration';
}

function parseReport(xmlFile: string): FailedTest[] {
  const xml = readFileSync(xmlFile, 'utf8');
  const appRoot = jestAppRoot(xmlFile);

  return appRoot === null ? parseJUnitReport(xml) : parseJestJUnitReport(xml, appRoot);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
