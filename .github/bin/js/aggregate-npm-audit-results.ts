#!/usr/bin/env node

import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

interface FixAvailable {
  name: string;
  version: string;
  isSemVerMajor: boolean;
}

interface RootAdvisory {
  ghsa: string | null;
  title: string;
  severity: string;
  url: string;
  packageName: string;
  range: string;
  affectedPackages: string[];
  fixAvailable: false | FixAvailable;
}

interface AuditExecutionResult {
  packageName: string;
  workingDirectory: string;
  ignoredCount: number;
  status: 'passed' | 'failed';
  advisoryCount: number;
  advisories: RootAdvisory[];
  error?: string;
}

interface IssuePayload {
  issueTitle: string;
  issueBody: string;
  commentBody: string;
}

const ISSUE_TITLE = 'Nightly npm audit failures';
const ISSUE_MARKER = '<!-- nightly-npm-audit-failures -->';

function collectJsonFiles(directory: string): string[] {
  const entries = readdirSync(directory, { withFileTypes: true });

  return entries.flatMap((entry) => {
    const fullPath = join(directory, entry.name);

    if (entry.isDirectory()) {
      return collectJsonFiles(fullPath);
    }

    return entry.isFile() && entry.name.endsWith('.json') ? [fullPath] : [];
  });
}

function parseResults(directory: string): AuditExecutionResult[] {
  const jsonFiles = collectJsonFiles(resolve(directory));
  return jsonFiles.map((filePath) => JSON.parse(readFileSync(filePath, 'utf8')) as AuditExecutionResult);
}

function formatAdvisory(advisory: RootAdvisory): string {
  const advisoryUrl = advisory.ghsa
    ? `https://github.com/advisories/${advisory.ghsa}`
    : advisory.url || 'No advisory URL';

  return `- ${advisory.severity}: ${advisory.title} (${advisory.packageName} ${advisory.range}) ${advisoryUrl}`;
}

function buildReportLines(results: AuditExecutionResult[]): string[] {
  const failedResults = results
    .filter((result) => result.status === 'failed')
    .sort((left, right) => left.packageName.localeCompare(right.packageName));

  if (failedResults.length === 0) {
    throw new Error('No failed audit results found.');
  }

  const runUrl = process.env['GITHUB_SERVER_URL'] && process.env['GITHUB_REPOSITORY'] && process.env['GITHUB_RUN_ID']
    ? `${process.env['GITHUB_SERVER_URL']}/${process.env['GITHUB_REPOSITORY']}/actions/runs/${process.env['GITHUB_RUN_ID']}`
    : 'GitHub Actions run URL unavailable';
  const refName = process.env['GITHUB_REF_NAME'] ?? 'unknown-ref';
  const repository = process.env['GITHUB_REPOSITORY'] ?? 'unknown-repository';

  const lines = [
    `Repository: ${repository}`,
    `Ref: ${refName}`,
    `Run: ${runUrl}`
  ];

  for (const result of failedResults) {
    lines.push('');
    lines.push(`### ${result.packageName}`);
    lines.push(`Path: \`${result.workingDirectory}\``);
    if (result.error) {
      lines.push(`- audit execution failed: ${result.error}`);
    }
    result.advisories.forEach((advisory) => lines.push(formatAdvisory(advisory)));
  }

  return lines;
}

function buildIssuePayload(results: AuditExecutionResult[]): IssuePayload {
  const reportLines = buildReportLines(results);
  const issueBody = [
    ISSUE_MARKER,
    '# Nightly npm audit failures',
    '',
    'This issue tracks failing scheduled npm audits. New nightly failures are added as comments.',
    '',
    'Latest failure:',
    '',
    ...reportLines
  ].join('\n').trimEnd();
  const commentBody = [
    ISSUE_MARKER,
    '## Nightly audit failure update',
    '',
    ...reportLines
  ].join('\n').trimEnd();

  return {
    issueTitle: ISSUE_TITLE,
    issueBody,
    commentBody
  };
}

function main(): void {
  const resultDirectory = process.argv[2];
  const payloadFile = process.argv[3];

  if (!resultDirectory || !payloadFile) {
    console.error('Usage: node aggregate-npm-audit-results.ts <result-directory> <payload-file>');
    process.exit(1);
  }

  const stats = statSync(resultDirectory, { throwIfNoEntry: false });
  if (!stats || !stats.isDirectory()) {
    console.error(`Audit result directory not found: ${resultDirectory}`);
    process.exit(1);
  }

  const results = parseResults(resultDirectory);
  if (results.length === 0) {
    console.error(`No audit result JSON files found in ${resultDirectory}`);
    process.exit(1);
  }

  const payload = buildIssuePayload(results);

  writeFileSync(payloadFile, `${JSON.stringify(payload)}\n`, 'utf8');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
