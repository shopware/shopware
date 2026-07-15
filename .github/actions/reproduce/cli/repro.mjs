#!/usr/bin/env node
import fs from 'node:fs';
import { die, blockedResult, readJson, writeJson, FILES } from '../bundle.mjs';

/**
 * Terminal-facing command dispatcher for the `repro` executable.
 *
 * This file owns argument parsing, user-facing command names, exit behavior, and small CLI-only
 * commands. Named repro subcommands live in `commands/`; executors stay in `executors/`.
 */
async function runCli(argv) {
  const [command, ...args] = argv;
  const commands = buildCommands(args);
  const run = commands[command];

  if (!run) {
    console.error(`repro: unknown command ${command ? `'${command}'` : '(none)'}\n`);
    console.error('commands: validate | reset | seed | check | try | giveup | verify | schema | search | version | blocked-result');
    process.exit(2);
  }

  await run();
}

/**
 * Creates command handlers with the current invocation arguments captured for CLI-only commands.
 *
 * Most commands delegate to command modules. `giveup` and `blocked-result` live here because they
 * are terminal/reporting affordances, not test execution behavior.
 */
function buildCommands(args) {
  return {
    validate: async () => (await import('./commands/validate.mjs')).validate(),
    reset: async () => (await import('./commands/reset.mjs')).reset(),
    seed: async () => {
      try {
        await (await import('./commands/seed.mjs')).seed();
      } catch (err) {
        die(err.message);
      }
    },
    check: async () => {
      const { ok } = await (await import('./commands/check.mjs')).check();
      if (!ok) {
        process.exit(1);
      }
    },
    try: async () => (await import('./commands/try.mjs')).tryBundle(),
    verify: async () => (await import('./commands/verify.mjs')).verify(),
    schema: async () => (await import('./commands/inspect.mjs')).schemaCommand(args[0]),
    search: async () => (await import('./commands/inspect.mjs')).searchCommand(args[0], args[1]),
    version: async () => (await import('./commands/inspect.mjs')).versionCommand(args[0]),
    giveup: async () => {
      fs.writeFileSync('giveup.txt', `${args.join(' ') || 'no reliable reproduction found'}\n`);
      console.log('recorded give-up; the deterministic report will post an "incomplete" comment');
    },
    'blocked-result': async () => writeBlockedResult(args),
  };
}

/**
 * Writes a canonical blocked result when a workflow leg cannot produce one itself.
 *
 * This keeps workflow YAML from hand-assembling the result contract while still making the command
 * clearly terminal-facing: it exists to repair a workflow artifact, not to execute a reproduction.
 */
function writeBlockedResult(args) {
  const [target, reason, version] = args;
  if (!target) {
    die('blocked-result: missing <target> (e.g. trunk)', 2);
  }
  const plan = { ...readJson(FILES.plan, {}) };
  if (version) {
    plan.version = version;
  }
  const detail = reason || `${target} environment did not come up`;
  writeJson(FILES.result, blockedResult(plan, target, detail));
  console.log(`wrote ${FILES.result}: blocked ${target} leg (${detail})`);
}

await runCli(process.argv.slice(2));
