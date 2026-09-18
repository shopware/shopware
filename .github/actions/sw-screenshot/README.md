# sw-screenshot

Toolchain for the [`sw-screenshot`](../../workflows/sw-screenshot.md) agentic workflow, which posts
context screenshots on issues and pull requests that are about a visual change.

## What a run does

1. Fetches the issue or pull request into `context.md`, picks the mode into `mode.md`, and decides
   what to provision: **trunk** for an issue, the pull request's **merge base** for a pull request.
2. Provisions the shop, moves it out of the workspace, seeds demo data, publishes it on port 80 and
   registers `host.docker.internal` as a storefront domain.
3. Hands the agent a browser and the `shot` CLI. The agent judges whether the change is visual,
   reaches the state, and photographs it.
4. For a pull request, `shot swap head` asks a host-side worker to move the same instance to the
   head ref, so the agent can shoot the same state again.
5. The agent registers its images with gh aw's `upload_asset` and writes the comment itself.

Every run ends in exactly one comment: the screenshots, "not a visual change", or one sentence
naming what failed.

## Why it is shaped this way

**One instance, one database.** A pull request's before and after come from the same install with
only the source tree swapped, so seeded data survives the transition and never has to be recreated
identically on a second machine.

**Seeding is written, verifying is looked at.** Entities go in through the acceptance suite's
`TestDataService`, which handles ids, foreign keys and sales-channel visibility. The agent then
navigates to the page and looks — the screenshot is the deliverable, so verification is already on
the critical path. This is what prevents the "seeded but invisible" failure a declarative fixture
format cannot catch.

**Demo data is not optional.** A clean install photographs as an empty page. `framework:demodata`
makes most reported states reachable by navigation alone; it needs `APP_ENV=prod` *and*
`shopware/dev-tools`, both of which `shopware/setup-shopware` already provides.

**The agent can talk to the shop but cannot touch it.** The sandbox mounts `GITHUB_WORKSPACE`
read-write, so anything left there is code the agent could rewrite before the host executes it.
Provisioning moves the shop to `$RUNNER_TEMP`, which the sandbox does not mount at all; the agent
reaches it over HTTP only.

That is also why the swap runs on the host. The sandbox's PHP has neither PDO nor iconv, so
`bin/console` cannot run there. `shot swap` writes a request; `steps/swap-worker.sh` does the work
outside the sandbox. Its entire interface is the word `head` or `base`, checked against a literal
allowlist, and it executes `swap.sh` from a read-only copy taken before the agent started — so the
agent controls *when* the swap happens and nothing else.

**The sandbox dictates the network shape.** The agent reaches host ports 80, 443 and 8080 only and
addresses the runner as `host.docker.internal`. gh aw's own MCP gateway owns 8080, so the shop
listens unprivileged on 8000 and a forwarder publishes it on 80. Shopware answers 400 to an
unregistered Host, hence the extra sales-channel domain. Provisioning probes the agent's exact path —
port 80, that Host header, expecting 200 — so a network misconfiguration fails the step instead of
costing an agent run.

**No verdict.** This workflow does not decide whether a bug is real or a fix is correct; that is
`sw-triage` and `sw-review`.

## CLI

```
shot info                 print the shop URL and admin login
shot seed <script.ts>     run a seed script with TestDataService pre-wired
shot swap <head|base>     ask the host to repoint the shop at the other ref
shot diff <a> <b> [out]   heat map of the pixels that changed between two screenshots
```

A seed script default-exports a function and receives everything it needs:

```ts
export default async ({ data, api, salesChannel }) => {
    const product = await data.createBasicProduct({ name: 'Repro Product' });
    await data.assignProductCategory(product.id, salesChannel.navigationCategoryId);
    await data.clearCaches();
};
```

`shot diff` exists because spacing changes are the ones a reviewer's eye slides past. It refuses
images of different sizes: padding one to match the other marks every shifted pixel as changed,
which reads as a total rewrite and hides the real difference. The policy makes it mandatory for a
before-and-after pair — the count is what the agent may report, rather than its impression of two
screenshots.

## Layout

```
lib/shop.ts                    resolves the shop's URL and login from the environment
lib/admin-api.ts               Admin API client shaped like Playwright's APIRequestContext
lib/data-service.ts            wires TestDataService to the live shop's storefront sales channel
lib/diff.ts                    pixel comparison behind `shot diff`
cli/                           the `shot` entry point and its commands
steps/fetch-context.sh         issue/PR context, mode selection, provision ref
steps/provision.sh             demo data, relocation, web server, port-80 forwarder, readiness
steps/register-sandbox-domain.sh
steps/install-cli.sh           the `shot` shim and the swap's coordinates
steps/swap-worker.sh           host-side executor for `shot swap`
steps/swap.sh                  the swap itself: fetch, checkout, migrate, rebuild, verify
steps/collapse-previous.sh     minimises earlier runs' comments (run by the result processor)
tests/                         node:test units and plain-bash tests for the shell steps
```

`lib/admin-api.ts` exists because the acceptance test suite does not export `AdminApiContext` from
its package root, while `TestDataService` needs one. It only ever calls four methods on its client,
so a duck-typed stand-in suffices. If that export lands upstream, this file can go.

## Constraints worth knowing

- **The merge base must be recent.** Provisioning resolves Composer dependencies against today's
  Packagist; a base more than a few weeks old can pull in a newer Twig or Symfony than that commit
  tolerates and the Administration 500s. `allow-insecure-versions` covers the advisory blocks that
  appear in the meantime, not this.
- **gh aw runs one agent per workflow.** There is no second agent phase, which is why the swap is
  agent-triggered and host-executed rather than a deterministic step between two agent runs.
- **Firewall denials appear in the comment.** gh aw appends every blocked domain to the comment it
  posts, and Chromium reaches for seven Google hosts of its own accord — autofill, GCM check-in,
  component updates, network time, account consistency. Launch flags do not stop it: Playwright
  already passes `--disable-background-networking`, `--disable-component-update` and
  `--disable-sync`, and a second `--disable-features` switch replaces Playwright's list rather than
  extending it. The `chrome` bundle and `www.gstatic.com` are therefore allow-listed, and a
  throwaway CI browser is left to phone home rather than putting a warning on every comment.

## Running the tests

```bash
cd .github/actions/sw-screenshot
npm ci
npm test                    # node:test units
npm run typecheck           # tsc --noEmit, strict
npx eslint .
bash tests/bash/run.sh      # shell steps
```

TypeScript runs through Node's native type-stripping — there is no build step, so what is committed
is exactly what executes. A test guards against the syntax that mode cannot strip (parameter
properties, enums), because `tsc` accepts them and the failure otherwise appears only on a runner.
