/**
 * @sw-package framework
 *
 * Human-readable rendering for the extension tooling CLI. The renderers are
 * pure: they take a silent result and return the full report string, with color
 * applied via picocolors (support decided once at import time — FORCE_COLOR, a
 * TTY, or env.CI turn it on; NO_COLOR wins). The specs strip ANSI before
 * semantic assertions and report.spec/color.spec.ts covers the colored path.
 *
 * This module is the stable entry point; the rendering lives in focused
 * siblings so each report reads top-down:
 * - report-check.ts    — `renderCheckReport` (admin:check-extensions)
 * - report-setup.ts    — `renderSetupReport` (admin:setup-extension-tooling)
 * - report-summary.ts  — the triage summary and skipped-target remediation
 * - report-guidance.ts — per-tool why/fix guidance, next steps, file ownership
 */

export { renderCheckReport } from './report-check';
export { renderSetupReport } from './report-setup';
export { describeNextStep, describeToolGuidance } from './report-guidance';
export type { ToolGuidance } from './report-guidance';
