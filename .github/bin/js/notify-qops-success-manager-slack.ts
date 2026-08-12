#!/usr/bin/env node

// Posts the qops-success-manager pipeline-check summary to Slack via an
// incoming webhook. The summary (produced by the qops-success-manager gh-aw
// workflow's agent step) already contains the Slack-ready text — this
// script's only job is building the webhook payload and doing the HTTP call,
// with a visible fallback when the agent didn't produce one. See
// .agents/skills/qops-success-manager/SKILL.md for what the summary means.

import { readFileSync } from 'node:fs';

export interface Summary {
  text?: string;
}

/** Pure so the fallback-vs-happy-path branches are unit-testable without a network call. */
export function buildSlackPayload(summary: Summary, runUrl: string): { text: string } {
  const text = summary.text?.trim();
  if (!text) {
    return { text: `qops-success-manager: the agent run did not produce a summary. See ${runUrl}` };
  }

  return { text: `${text}\nSee ${runUrl}` };
}

async function main(): Promise<void> {
  const [summaryPath] = process.argv.slice(2);
  if (!summaryPath) {
    throw new Error('Usage: notify-qops-success-manager-slack.ts <summary-json-path>');
  }

  const webhookUrl = process.env.SLACK_WEBHOOK_URL;
  if (!webhookUrl) {
    throw new Error('SLACK_WEBHOOK_URL is not set; cannot send the notification.');
  }

  const runUrl = process.env.RUN_URL ?? '(no run URL provided)';

  let summary: Summary = {};
  try {
    summary = JSON.parse(readFileSync(summaryPath, 'utf8')) as Summary;
  } catch {
    console.warn(`No readable summary file at ${summaryPath} — posting a fallback notice.`);
  }

  const payload = buildSlackPayload(summary, runUrl);

  const response = await fetch(webhookUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const body = await response.text();
    throw new Error(`Slack webhook returned HTTP ${response.status}: ${body.slice(0, 500)}`);
  }

  console.log('Slack notification sent.');
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error: unknown) => {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
  });
}
