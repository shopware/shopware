---
title: Generate default tax rate migrations from a dataset
date: 2026-11-26
area: checkout
tags: [tax, vat, tax-rule, migration, defaults]
---

## Context

A new installation ships entire-country tax rules for the EU-27 and GB.
`Migration1589359936AddTaxCountryRules` introduced them in 6.3, and no artifact in the repository has stated what they should be since.
The defaults exist only as the outcome of replaying every migration against an empty database: there is no file to read, diff or review, and the drift check added in [#23417](https://github.com/shopware/shopware/pull/23417) has to install Shopware and dump `tax_rule` to learn its own baseline.

Migrations run once per database and never again, and every later change is a new migration backported to every supported branch.
In six and a half years exactly one exists: `Migration1794852779UpdateDefaultTaxRules` ([#23006](https://github.com/shopware/shopware/pull/23006)) corrected six countries, two of them wrong for more than two years, and was written only after a merchant reported the drift.

[#23032](https://github.com/shopware/shopware/issues/23032) additionally asks for rates announced for the next six months, so that a correction ships before it takes effect rather than after.

## Decision

The default rates are stored as a dataset in the repository, and migrations are generated based it.

Nothing reads the dataset at runtime. Loading and handling tax rates remains unchanged.
The dataset exists for these reasons:

- one look what rates are currently installed without setting up a Shop instance
- single source of truth for database migrations and tax rate validation

### The dataset

`src/Core/System/Tax/default-tax-rates.json` holds one entry per shipped country, keyed by ISO code, in the three tax groups the seed established.
An `upcoming` entry carries a rate that is published but not yet in force.

```json
{
  "FI": {
    "Standard rate": 25.5,
    "Reduced rate": 13.5,
    "Reduced rate 2": 10,
    "upcoming": [{ "activeFrom": "2027-01-01", "Standard rate": 26 }]
  }
}
```

Rates that do not fit the three groups stay out, as #23006 and #23032 both state.
**A country whose real tax law needs more than three brackets is a merchant configuration, not a default.**

The file answers "what rate does a fresh installation ship" without a database, and a correction becomes a reviewable diff for none technical reviewers.

### Two commands own the file

`bin/console sync:tax:default-rates` fetches the published rates from the EU Commission's TEDB and from GOV.UK for the United Kingdom, and rewrites the dataset.
It is the only writer, and it keeps the plausibility guards that reject an implausible rate, a stale period or a truncated response rather than committing it.

`bin/console make:tax-migration` emits the migration and its test for the current dataset.

Both commands write into the shopware/shopware working tree, yet everything under `src/Core` ships in `shopware/core` and is present in every vendor installation.
Three decisions keep platform tooling from posing as shop tooling there:

- The commands live in the DevOps area under its command namespaces — `sync:tax:default-rates` beside `sync:composer:version`, `make:tax-migration` beside `make:coverage` — not under `tax:`, where the command list would present rewriting the platform's defaults as an operation on the shop's own tax rates.
- They are registered in the DevOps bundle's `services_dev.php`, which loads only when the kernel environment is `dev`, exactly as `make:coverage` already is. A production installation does not contain the commands at all.
- A `dev` environment alone does not prove a monorepo checkout, because a plugin developer's vendor installation runs `dev` too. Each command therefore asserts it runs inside a `shopware/platform` project and aborts otherwise: every path it writes resolves into `vendor/` anywhere else.

Drift detection is then a diff, not a second implementation of the rate comparison: a scheduled job runs both commands and inspects the working tree.
A clean tree is the record that the review found nothing and changed nothing, which is what step 3 of #23032 asks for.
A dirty tree is the correction, already written, so the job opens a pull request instead of an issue.
Deduplicating reports, fingerprinting them and reconciling them against issues that are already open all stop being necessary: an open pull request is its own deduplication.

A second check asserts the opposite direction.
`tax_rule` after every migration has run must equal the dataset, which fails when the dataset is edited without generating the migration that carries the change.

### Migrations are generated, never hand-written

The dataset is read when a migration is authored, not when it runs.
The generator therefore inlines the rates into the migration it emits.

A generated migration states the target rates, not the change.

```php
private const RATES = [
    'FI' => [self::STANDARD_RATE => 26, self::REDUCED_RATE => 13.5, self::REDUCED_RATE_2 => 10],
    // one entry per shipped country
];

public function update(Connection $connection): void
{
    foreach (self::RATES as $iso => $rates) {
        foreach ($rates as $taxName => $rate) {
            $this->updateRate($connection, $iso, $taxName, $rate);
        }
    }
}
```

`updateRate()` sets the rate for that country and tax group wherever `tax_rule.updated_at IS NULL`, and nowhere else.
It does not name the rate it replaces.

Making the replaced rate a precondition is harmful.
An untouched rule that holds neither the expected old value nor the new one, because it was created between two corrections, missed a backport, or was written to outside the DAL, is skipped in silence by a conditional update and left wrong indefinitely.
That is the drift this decision exists to remove.
A convergent statement repairs it instead, is re-runnable by construction as rule 2 requires, and leaves the generator a pure function of the dataset with no notion of a previous version.

A country and group that holds no rule yet is inserted, with the conditional `INSERT` that `Migration1794852779UpdateDefaultTaxRules::addReducedRate2()` established: only where the country already has a standard rule and holds no rule in that group.
A rule a merchant has edited blocks both paths, so it is neither updated nor duplicated.
An entry removed from the dataset emits nothing; a default once shipped is never deleted from a merchant's database.

### Announced changes ship ahead of their date

`tax_rule.active_from` carries a future rate end to end: `EntireCountryRuleTypeFilter::match()` defers to `AbstractTaxRuleTypeFilter::isTaxActive()`, and `TaxRuleCollection::latestActivationDate()` picks the newest activated rule once several match.
Nothing in the seeded defaults uses it today.

An `upcoming` entry emits an `INSERT` carrying `active_from` instead of an `UPDATE`, guarded on no rule existing for that country, tax group and activation date.
The current rule keeps serving until that date and is not modified, so a merchant who has edited it is unaffected either way.
Once the date passes, the entry moves into the country's current rates and the superseded row is left in place.

`latestActivationDate()` reduces with `$item->getActiveFrom() > $result->getActiveFrom()`, which compares a `DateTimeInterface` against the `null` of a rule seeded without an activation date.
No shipped data exercises that comparison, so the first generated migration using `activeFrom` comes with a test that pins it.

### Who does what, and when

| Step | Done by | When |
| ---- | ------- | ---- |
| Rewrite the dataset from the published rates | `sync:tax:default-rates` | Monthly, on a schedule |
| Generate the migration and its test | `make:tax-migration`, in the same run | Only when the dataset changed |
| Open one pull request carrying dataset, migration and test | The same run | Only when the dataset changed |
| Accept or reject the rates | Codeowner of the dataset | On that pull request |
| Set the `backport-` labels | Reviewer | Before merge |
| Cherry-pick onto the supported branches | `backport.yml` | On merge |
| Assert `tax_rule` equals the dataset | CI | Every pull request touching the dataset or a tax migration |

Monthly rather than the two months #23032 asks for, because rate changes take effect on the first of a month and a run that finds nothing costs nothing.
A run that cannot reach a source fails and opens nothing: an unreachable authority is not evidence that a rate is unchanged.

Acceptance is the only human step, and it is the only one that should be.
A rate is a legal fact published by an authority, so the review exists to let the checkout team see a change before it ships, not to derive the number a second time.
`default-tax-rates.json` gets a codeowner entry for that team.

The generated migration goes into the oldest supported major namespace, `V6_6` today, as #23006 did.
A migration authored in the current development namespace cannot be cherry-picked onto a branch where that namespace does not exist.

The check in #23417 is the interim form of the freshness step and is replaced once the commands exist.
Its parsing, in particular the TEDB envelope and the scoping of the rate element, is ported rather than derived from the specifications a second time.

## Consequences

* The current defaults are readable, diffable and reviewable without a database; a rate change appears in a pull request as a number, not as SQL.
* A generated migration converges on the dataset instead of applying a delta, so an installation that missed an earlier correction is repaired by the next one rather than staying wrong indefinitely.
* A rule written outside the DAL, leaving `updated_at` null, is overwritten where a conditional update would have left it alone; writing tax rules outside the DAL is outside the contract either way.
* A rate correction reaches a merchant without anyone having noticed anything, and nothing in the loop depends on a recurring calendar task, which is the part of #23032 that had no owner and why the 2020 rates survived six years.
* Nothing changes for an existing installation: the runtime read path, the seeded rows and merchant edits are untouched, and no migration is rewritten.
* Announced changes can ship before they take effect, at the cost of a second `tax_rule` row per announced change and a test covering the `activeFrom` comparison.
* Two commands are new tooling to maintain, and a dataset edit that bypasses the generator produces no migration at all; the check asserting `tax_rule` against the dataset is what catches that.
* The commands follow the DevOps tooling pattern: absent from a production installation, present but refusing to run in a `dev` environment outside the monorepo. The scheduled job has to boot the kernel with `APP_ENV=dev` for them to exist.
* Both checks still need an installed Shopware, because the commands boot the kernel; what disappears is the second implementation of the rate comparison, not the installation step.
