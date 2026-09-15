/**
 * Trusted deterministic command for writing the official leg `result.json`.
 *
 * This command is not agent-facing. It is invoked only by guarded workflow steps and requires
 * `REPRO_ALLOW_VERIFY=1`, preventing the agent shell from producing authoritative verdict input.
 */
import { FILES, die } from '../../bundle.ts';
import { fullRun } from '../full-run.ts';

/**
 * Runs the trusted deterministic leg sequence and writes the official result.
 *
 * The environment gate prevents the agent-facing CLI from producing authoritative verdict input;
 * only guarded workflow steps set `REPRO_ALLOW_VERIFY=1`.
 */
export function verify() {
  if (process.env.REPRO_ALLOW_VERIFY !== '1') {
    die('verify is reserved for the deterministic post-step; the agent does not run it (use `try` for a preview)');
  }
  return fullRun({ target: process.env.TARGET || 'reported', out: FILES.result, reset: true });
}
