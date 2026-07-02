#!/usr/bin/env node
// The reproduce CLI. Agent-facing commands are composable primitives — the agent decides how to
// combine them while exploring. `verify` is the trusted, gated post-step command (not for the agent).
//
//   repro validate   check the bundle contract (+ sanitize/inspect the spec, no execution)
//   repro reset      restore the clean DB snapshot + clear cache
//   repro seed       apply fixtures.json to the live shop (surfaces the Sync result)
//   repro check      load each seeded_readiness route and assert the seeded markers render
//   repro try        OPTIONAL preview: run the spec on the current state → builder-result.json
//   repro giveup     record that no reliable reproduction was found
//   repro verify     TRUSTED: reset → seed → run → result.json  (post-step only; gated)
import fs from 'node:fs';
import { die } from './lib.mjs';

const [command, ...args] = process.argv.slice(2);

const commands = {
  validate: async () => (await import('./validate.mjs')).validate(),
  reset: async () => (await import('./reset.mjs')).reset(),
  seed: async () => {
    try { await (await import('./seed.mjs')).seed(); }
    catch (err) { die(err.message); }
  },
  check: async () => {
    const { ok } = await (await import('./check.mjs')).check();
    if (!ok) process.exit(1);
  },
  try: async () => (await import('./try.mjs')).tryBundle(),
  verify: async () => (await import('./verify.mjs')).verify(),
  giveup: async () => {
    fs.writeFileSync('giveup.txt', `${args.join(' ') || 'no reliable reproduction found'}\n`);
    console.log('recorded give-up; the deterministic report will post an "incomplete" comment');
  },
};

const run = commands[command];
if (!run) {
  console.error(`repro: unknown command ${command ? `'${command}'` : '(none)'}\n`);
  console.error('commands: validate | reset | seed | check | try | giveup | verify');
  process.exit(2);
}
await run();
