#!/usr/bin/env node

// Aggregates the junit.xml artifacts of a failed scheduled PHPUnit run into a
// tracking-issue payload (consumed by .github/workflows/report-phpunit-failures.yml).
// Failing tests are grouped by owning domain, resolved from the test file's
// #[Package] attribute with a fallback to the dominant package of the mirrored
// src/ directory. This is an inventory, not a triage: clustering failures into
// root causes and filing per-domain issues stays a manual step — see
// .agents/skills/nightly-triage/SKILL.md.

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
  parent: IssueContent;
  domains: DomainIssue[];
}

export const ISSUE_MARKER = '<!-- nightly-phpunit-failures -->';

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

const PACKAGE_ATTRIBUTE = /#\[Package\('([^']+)'\)\]/;

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

function tryReadPackageKey(file: string): string | null {
  try {
    return readFileSync(file, 'utf8').match(PACKAGE_ATTRIBUTE)?.[1] ?? null;
  } catch {
    return null;
  }
}

// Dominant #[Package] key among the PHP files of one directory (non-recursive).
function dominantPackageKey(directory: string): string | null {
  let entries;
  try {
    entries = readdirSync(directory, { withFileTypes: true });
  } catch {
    return null;
  }

  const counts = new Map<string, number>();
  for (const entry of entries) {
    if (!entry.isFile() || !entry.name.endsWith('.php')) {
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

  const ownKey = tryReadPackageKey(join(repoRoot, testFile));
  if (ownKey) {
    return ownKey;
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

function testCount(count: number): string {
  return count === 1 ? '1 failing test' : `${count} failing tests`;
}

const TRIAGE_NOTE =
  'Grouping uses the test files\' `#[Package]` markers only. Root-cause clustering, routing overrides, ' +
  'and re-routing between the domain sub-issues are the deep-triage pass, see `.agents/skills/nightly-triage/SKILL.md`.';

function buildParentLines(groups: DomainGroup[], runUrl: string): string[] {
  const totalCount = groups.reduce((sum, group) => sum + group.tests.length, 0);
  const lines = [`Run: ${runUrl}`, `Failing tests: ${totalCount}`];

  if (groups.length === 0) {
    lines.push('');
    lines.push(
      'No junit reports were produced: the failure is outside PHPUnit, or a shard died before reporting. Check the run logs.'
    );
    return lines;
  }

  lines.push('');
  for (const group of groups) {
    const packageKeys = group.packageKeys.size > 0 ? [...group.packageKeys].sort().join(', ') : 'none resolved';
    lines.push(`- **${group.label}**: ${testCount(group.tests.length)} (package keys: ${packageKeys})`);
  }
  lines.push('');
  lines.push('Per-domain details live in the sub-issues.');
  lines.push('');
  lines.push(TRIAGE_NOTE);

  return lines;
}

function buildDomainLines(group: DomainGroup, runUrl: string): string[] {
  const lines = [`Run: ${runUrl}`, `Failing tests: ${group.tests.length}`, ''];

  for (const test of group.tests.slice(0, MAX_TESTS_PER_DOMAIN)) {
    lines.push(formatTest(test));
  }
  if (group.tests.length > MAX_TESTS_PER_DOMAIN) {
    lines.push(`- …and ${group.tests.length - MAX_TESTS_PER_DOMAIN} more, see the run logs.`);
  }

  return lines;
}

export function buildIssuePayload(issueTitle: string, groups: DomainGroup[], runUrl: string): IssuePayload {
  const parentLines = buildParentLines(groups, runUrl);
  const parent: IssueContent = {
    issueTitle,
    issueMarker: ISSUE_MARKER,
    issueBody: [
      ISSUE_MARKER,
      `# ${issueTitle}`,
      '',
      'This issue tracks failing scheduled PHPUnit runs: one sub-issue per owning domain, new failures are added as comments.',
      '',
      'Latest failure:',
      '',
      ...parentLines,
    ]
      .join('\n')
      .trimEnd(),
    commentBody: [ISSUE_MARKER, '## Scheduled PHPUnit failure update', '', ...parentLines].join('\n').trimEnd(),
  };

  const domains = groups.map((group): DomainIssue => {
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
        'Failing scheduled PHPUnit tests grouped to this domain by their `#[Package]` markers. New failures are added as comments.',
        '',
        'Latest failure:',
        '',
        ...lines,
      ]
        .join('\n')
        .trimEnd(),
      commentBody: [marker, '## Scheduled PHPUnit failure update', '', ...lines].join('\n').trimEnd(),
    };
  });

  return { parent, domains };
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

  const repoRoot = process.env['GITHUB_WORKSPACE'] ?? process.cwd();
  const runUrl =
    process.env['GITHUB_SERVER_URL'] && process.env['GITHUB_REPOSITORY'] && process.env['GITHUB_RUN_ID']
      ? `${process.env['GITHUB_SERVER_URL']}/${process.env['GITHUB_REPOSITORY']}/actions/runs/${process.env['GITHUB_RUN_ID']}`
      : 'GitHub Actions run URL unavailable';

  const stats = statSync(reportDirectory, { throwIfNoEntry: false });
  const xmlFiles = stats?.isDirectory() ? collectXmlFiles(resolve(reportDirectory)) : [];

  const seen = new Set<string>();
  const failures: FailedTest[] = [];
  for (const xmlFile of xmlFiles) {
    for (const failure of parseJUnitReport(readFileSync(xmlFile, 'utf8'))) {
      const key = `${failure.className}::${failure.testName}`;
      if (!seen.has(key)) {
        seen.add(key);
        failures.push(failure);
      }
    }
  }

  const payload = buildIssuePayload(issueTitle, groupByDomain(failures, repoRoot), runUrl);

  writeFileSync(payloadFile, `${JSON.stringify(payload)}\n`, 'utf8');
  console.log(`${failures.length} failing tests aggregated from ${xmlFiles.length} junit reports.`);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
