/**
 * Command entrypoint for `repro check`.
 *
 * The implementation belongs to the Playwright executor because browser readiness is a Playwright
 * setup concern; this file keeps the terminal command surface stable.
 */
export { check } from '../../executors/playwright/readiness-check.ts';
