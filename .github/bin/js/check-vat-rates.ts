/**
 * Validate the default VAT rates a migrated Shopware database holds against the rates the
 * responsible tax authorities publish. `tax_rule` is the only baseline; the workflow dumps it from
 * a freshly migrated database and passes it in with `--current`.
 *
 * Standard-rate findings are authoritative. Reduced-rate findings are review candidates — see
 * parseTedbRates.
 *
 * Exit codes: 0 the rates match, 1 a difference was found, 2 a source was unreachable, malformed or
 * frozen and nothing was compared.
 *
 * Usage: node check-vat-rates.ts --current <path-to-db-dump.json> [--date YYYY-MM-DD]
 */

import { appendFileSync, readFileSync } from 'node:fs';

export const TEDB_ENDPOINT = 'https://ec.europa.eu/taxation_customs/tedb/ws/';

/** Without this header TEDB answers with a bare HTTP 404 instead of a SOAP fault. */
export const TEDB_SOAP_ACTION =
    'urn:ec.europa.eu:taxud:tedb:services:v1:VatRetrievalService/RetrieveVatRates';

const TEDB_SERVICE_NS = 'urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService';

/**
 * The request root sits in the service namespace but every one of its children sits in the types
 * namespace. Mixing them fails validation with "TEDB-ERR-2 - Request is not valid".
 */
const TEDB_TYPES_NS = 'urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService:types';

/** TEDB reports Greece under its vehicle-registration code; Shopware uses the ISO 3166-1 code. */
const ISO_OVERRIDES: Record<string, string> = { EL: 'GR' };

export const MEMBER_STATES = [
    'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU',
    'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
];

/**
 * TEDB still answers for "UK", but every field of that record is frozen at 2020-01-01 — correct
 * today only because the UK has not moved its standard rate since 2011.
 */
export const GOVUK_ENDPOINT = 'https://www.gov.uk/api/content/vat-rates';

export const UK_ISO = 'GB';

/** The tax classes Migration1589359936AddTaxCountryRules bound the rates to. */
export const STANDARD_TAX_CLASS = 'Standard rate';
export const REDUCED_TAX_CLASSES = ['Reduced rate', 'Reduced rate 2'];

/** The number of decimals `tax_rule`.`tax_rate` stores, as a DOUBLE(10,3). */
const SCALE = 3;

/** Above this the payload was misread, not a member state being unusual; Hungary holds the EU maximum at 27%. */
const MAX_PLAUSIBLE_RATE = 30;

/**
 * TEDB reports the start of the validity period covering the query, not the queried date. A record
 * nobody maintains keeps answering HTTP 200 with an old start, which is how the frozen UK record
 * gives itself away.
 */
const MAX_PERIOD_AGE_IN_MONTHS = 24;

/**
 * The `comment` field carries two unrelated things: a territorial qualifier ("VAT - Canary Islands -")
 * and an HTML legal citation marking the *national* rate ("<p>Art. 47 of the VAT Act.</p>", used by
 * BE, CZ, FR, IE, LU and LV). Only the former may be discarded — a plain "has a comment" test loses
 * Belgium's real 21%.
 */
const TERRITORIAL_COMMENT = /^VAT\s+-\s+.+?-\s*$/u;

const RESULT_BLOCK = /<vatRateResults\b[^>]*>([\s\S]*?)<\/vatRateResults>/g;
const SOAP_FAULT = /<(?:[\w.-]+:)?Fault\b[^>]*>[\s\S]*?<faultstring>([\s\S]*?)<\/faultstring>/i;

export type CountryRates = {
    iso: string;
    standard: string;
    reduced: string[];
};

export type StandardDrift = {
    iso: string;
    current: string;
    published: string;
};

export type ReducedCandidate = {
    iso: string;
    current: string[];
    published: string[];
};

export type Diff = {
    standardDrift: StandardDrift[];
    reducedCandidates: ReducedCandidate[];
};

/** A row of the `tax_rule` dump the workflow produces. */
export type CurrentRateRow = {
    iso: string;
    taxClass: string;
    taxRate: number | string;
};

/**
 * Anything that is not a verdict about the rates — unreachable endpoint, unparseable payload, frozen
 * record. Mapped to exit 2, so a broken source is never reported as a rate change.
 */
export class SourceError extends Error {}

/**
 * One canonical textual form, so the 19, "19" and "19.000" that the database, TEDB and GOV.UK
 * respectively hand over all compare equal. 19.0 -> "19", 9.50 -> "9.5".
 */
export function canonicalRate(value: number | string): string {
    const parsed = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(parsed)) {
        throw new SourceError(`unreadable rate value "${String(value)}"`);
    }

    return String(Number(parsed.toFixed(SCALE)));
}

/** Ascending by value, deduplicated, still strings. */
export function sortRates(rates: string[]): string[] {
    return [...new Set(rates)].sort((a, b) => Number(a) - Number(b));
}

export function decodeXmlEntities(text: string): string {
    return text
        .replace(/&lt;/gu, '<')
        .replace(/&gt;/gu, '>')
        .replace(/&quot;/gu, '"')
        .replace(/&apos;/gu, "'")
        .replace(/&#(\d+);/gu, (_, code: string) => String.fromCodePoint(Number(code)))
        .replace(/&amp;/gu, '&');
}

export function isTerritorialComment(comment: string): boolean {
    return TERRITORIAL_COMMENT.test(decodeXmlEntities(comment).trim());
}

export function buildTedbEnvelope(isoCodes: string[], date: string): string {
    const codes = isoCodes.map((iso) => `<t:isoCode>${iso}</t:isoCode>`).join('');

    // `from` plus `to` rather than `situationOn`, which the schema marks deprecated.
    return (
        '<?xml version="1.0" encoding="UTF-8"?>' +
        `<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:svc="${TEDB_SERVICE_NS}" xmlns:t="${TEDB_TYPES_NS}">` +
        '<soapenv:Body><svc:retrieveVatRatesReqMsg>' +
        `<t:memberStates>${codes}</t:memberStates><t:from>${date}</t:from><t:to>${date}</t:to>` +
        '</svc:retrieveVatRatesReqMsg></soapenv:Body></soapenv:Envelope>'
    );
}

function tag(block: string, name: string): string | null {
    const match = new RegExp(`<${name}>([\\s\\S]*?)</${name}>`, 'u').exec(block);

    return match ? match[1] : null;
}

function rateChild(block: string, name: string): string | null {
    const match = new RegExp(`<rate>[\\s\\S]*?<${name}>([\\s\\S]*?)</${name}>`, 'u').exec(block);

    return match ? match[1].trim() : null;
}

/** `situationOn` arrives with a trailing UTC offset, e.g. "2026-07-01+02:00". */
function periodStart(block: string): Date {
    const raw = (tag(block, 'situationOn') ?? '').trim().slice(0, 10);
    const parsed = new Date(`${raw}T00:00:00Z`);

    if (!/^\d{4}-\d{2}-\d{2}$/u.test(raw) || Number.isNaN(parsed.getTime())) {
        throw new SourceError(`unreadable situationOn value "${raw}"`);
    }

    return parsed;
}

function monthsBefore(date: Date, months: number): Date {
    const shifted = new Date(date.getTime());
    shifted.setUTCMonth(shifted.getUTCMonth() - months);

    return shifted;
}

/**
 * TEDB answers ~40 entries per country, nearly all scoped to a CN code, a CPA code, a product
 * category or a single territory; taking the first `DEFAULT` entry reports Spain at 7% (Canary
 * Islands) instead of 21%. These rules were validated against all 27 member states.
 *
 * Standard rate: `DEFAULT`, no category, no goods codes, non-territorial comment, and exactly one
 * distinct value must remain.
 *
 * Reduced rates: `REDUCED_RATE` entries carrying a CN or CPA code — a national reduced rate always
 * does and a territorial one never does, which excludes AT 19% (Jungholz), EL 17% (Aegean) and PT
 * 16%/22% (Azores, Madeira) — plus non-territorial `SUPER_REDUCED_RATE` entries, which must NOT
 * require codes, since Spain's 4% sits on a category alone. Shopware ships ES 4% and LU 3% as
 * ordinary reduced rates, so super-reduced is folded in.
 *
 * `PARKING_RATE` is excluded by type, which does not make the list authoritative: BE 12%, EL 13%
 * and PT 13% are each reported both as a parking rate and as a genuine reduced rate against goods
 * codes, and LU 14% is only the intermediate rate yet is reported the same way. Nothing in TEDB's
 * fields separates those, so a reduced-rate difference is a candidate to adjudicate, never a
 * correction to apply.
 */
export function parseTedbRates(xml: string, queriedOn: Date): Map<string, CountryRates> {
    const fault = SOAP_FAULT.exec(xml);
    if (fault) {
        throw new SourceError(`TEDB returned a SOAP fault: ${decodeXmlEntities(fault[1]).trim()}`);
    }

    const blocks = [...xml.matchAll(RESULT_BLOCK)].map((match) => match[0]);
    if (blocks.length === 0) {
        throw new SourceError('the TEDB response contains no vatRateResults');
    }

    const standard = new Map<string, string[]>();
    const reduced = new Map<string, string[]>();
    let newestPeriod: Date | null = null;

    for (const block of blocks) {
        const member = (tag(block, 'memberState') ?? '').trim().toUpperCase();
        if (member === '') {
            continue;
        }

        const iso = ISO_OVERRIDES[member] ?? member;
        const type = rateChild(block, 'type');
        const rawValue = rateChild(block, 'value');

        // A period that has not started yet must not count towards freshness either, or one
        // announced rate change would hide a frozen record.
        const period = periodStart(block);
        if (period.getTime() <= queriedOn.getTime() && (newestPeriod === null || period > newestPeriod)) {
            newestPeriod = period;
        }

        // NOT_APPLICABLE, OUT_OF_SCOPE and EXEMPTED entries carry no value.
        if (rawValue === null || rawValue === '') {
            continue;
        }

        const comment = tag(block, 'comment');
        const territorial = comment !== null && isTerritorialComment(comment);
        const hasGoodsCodes = /<(?:cnCodes|cpaCodes)[\s>]/u.test(block);
        const hasCategory = /<category[\s>]/u.test(block);
        const value = canonicalRate(rawValue);

        if (!standard.has(iso)) {
            standard.set(iso, []);
            reduced.set(iso, []);
        }

        if (type === 'DEFAULT') {
            if (!territorial && !hasCategory && !hasGoodsCodes) {
                standard.get(iso)?.push(value);
            }
        } else if (type === 'REDUCED_RATE') {
            if (!territorial && hasGoodsCodes) {
                reduced.get(iso)?.push(value);
            }
        } else if (type === 'SUPER_REDUCED_RATE') {
            if (!territorial) {
                reduced.get(iso)?.push(value);
            }
        }
    }

    assertFresh(newestPeriod, queriedOn);

    const rates = new Map<string, CountryRates>();

    for (const [iso, values] of standard) {
        const distinct = sortRates(values);

        if (distinct.length === 0) {
            throw new SourceError(`TEDB reported no standard VAT rate for "${iso}"`);
        }

        if (distinct.length > 1) {
            throw new SourceError(
                `TEDB reported more than one national standard VAT rate for "${iso}": ${distinct.join(', ')}`,
            );
        }

        const standardRate = Number(assertPlausible(distinct[0], `TEDB reports as the standard rate of "${iso}"`));

        rates.set(iso, {
            iso,
            standard: distinct[0],
            reduced: sortRates(reduced.get(iso) ?? []).filter((rate) => Number(rate) < standardRate),
        });
    }

    return rates;
}

function assertFresh(newestPeriod: Date | null, queriedOn: Date): void {
    if (newestPeriod === null) {
        throw new SourceError(
            `no TEDB rate period had started on ${queriedOn.toISOString().slice(0, 10)}`,
        );
    }

    if (newestPeriod < monthsBefore(queriedOn, MAX_PERIOD_AGE_IN_MONTHS)) {
        throw new SourceError(
            `TEDB looks frozen: its newest rate period starts ${newestPeriod.toISOString().slice(0, 10)}, ` +
                `which is more than ${MAX_PERIOD_AGE_IN_MONTHS} months before ${queriedOn.toISOString().slice(0, 10)}`,
        );
    }
}

/**
 * The answer box and the one-line summary are both hand-maintained, so the standard rate is read
 * from each and the two must agree. The reduced rate is anchored on its table label, which keeps
 * the page's historical note about the 2011 increase from 17.5% out of the result.
 */
export function parseGovUkRates(payload: string): CountryRates {
    let decoded: unknown;

    try {
        decoded = JSON.parse(payload);
    } catch {
        throw new SourceError('the GOV.UK response is not valid JSON');
    }

    if (typeof decoded !== 'object' || decoded === null || Array.isArray(decoded)) {
        throw new SourceError('the GOV.UK response is not a JSON object');
    }

    const record = decoded as { description?: unknown; details?: { body?: unknown } };
    const body = record.details?.body;
    const summary = record.description;

    if (typeof body !== 'string' || typeof summary !== 'string') {
        throw new SourceError('the GOV.UK response carries no rate description');
    }

    const standard = readRate(body, /standard\s+VAT\s+rate\s+is\s*(?:<[^>]+>\s*)*(\d+(?:\.\d+)?)\s*%/iu, 'the standard rate');
    const confirmation = readRate(summary, /standard\s+(\d+(?:\.\d+)?)\s*%/iu, 'the standard rate summary');

    if (standard !== confirmation) {
        throw new SourceError(
            `the GOV.UK page reports the standard rate as both ${standard}% and ${confirmation}%`,
        );
    }

    const reduced = readRate(body, /Reduced\s+rate[\s\S]*?(\d+(?:\.\d+)?)\s*%/iu, 'the reduced rate');

    return { iso: UK_ISO, standard, reduced: [reduced] };
}

function readRate(text: string, pattern: RegExp, what: string): string {
    const match = pattern.exec(text);

    if (!match) {
        throw new SourceError(`${what} could not be read from the GOV.UK page`);
    }

    return assertPlausible(canonicalRate(match[1]), `the GOV.UK page reports as ${what}`);
}

function assertPlausible(rate: string, context: string): string {
    const value = Number(rate);

    if (value <= 0 || value > MAX_PLAUSIBLE_RATE) {
        throw new SourceError(`the VAT rate "${rate}" ${context} is outside the plausible range`);
    }

    return rate;
}

/**
 * A country with no standard-rate row is skipped rather than reported as a zero rate:
 * Migration1589359936AddTaxCountryRules bails out entirely on a shop whose taxes had been touched,
 * so a missing row is a legitimate state.
 */
export function parseCurrentRates(rows: CurrentRateRow[]): Map<string, CountryRates> {
    const standard = new Map<string, string>();
    const reduced = new Map<string, string[]>();

    for (const row of rows) {
        const iso = row.iso.trim().toUpperCase();
        const rate = canonicalRate(row.taxRate);

        if (row.taxClass === STANDARD_TAX_CLASS) {
            standard.set(iso, rate);
        } else if (REDUCED_TAX_CLASSES.includes(row.taxClass)) {
            reduced.set(iso, [...(reduced.get(iso) ?? []), rate]);
        }
    }

    const rates = new Map<string, CountryRates>();

    for (const [iso, rate] of standard) {
        rates.set(iso, { iso, standard: rate, reduced: sortRates(reduced.get(iso) ?? []) });
    }

    return rates;
}

export function diffRates(
    current: Map<string, CountryRates>,
    published: Map<string, CountryRates>,
): Diff {
    const standardDrift: StandardDrift[] = [];
    const reducedCandidates: ReducedCandidate[] = [];

    for (const iso of [...published.keys()].sort()) {
        const publishedRates = published.get(iso);
        const currentRates = current.get(iso);

        // A country the shop does not have a rate for is not drift — see parseCurrentRates.
        if (!publishedRates || !currentRates) {
            continue;
        }

        if (currentRates.standard !== publishedRates.standard) {
            standardDrift.push({
                iso,
                current: currentRates.standard,
                published: publishedRates.standard,
            });
        }

        if (currentRates.reduced.join(',') !== publishedRates.reduced.join(',')) {
            reducedCandidates.push({
                iso,
                current: currentRates.reduced,
                published: publishedRates.reduced,
            });
        }
    }

    return { standardDrift, reducedCandidates };
}

/**
 * A stable digest of a finding set, so the workflow comments on its tracking issue only when the
 * finding changed instead of once a month forever.
 */
export function fingerprint(
    findings: Array<{ iso: string; current: string | string[]; published: string | string[] }>,
): string {
    const render = (value: string | string[]): string => (Array.isArray(value) ? value.join('/') : value);

    return findings
        .map((finding) => `${finding.iso}:${render(finding.current)}>${render(finding.published)}`)
        .sort()
        .join(' ');
}

// --- Reporting: which issues the findings above become. The `actions/github-script` step imports
// from here, so that the Octokit calls are all that is left inline in the workflow.

export const STANDARD_DRIFT = {
    marker: '<!-- eu-vat-standard-rate-drift -->',
    title: 'Default standard VAT rates no longer match the published rates',
} as const;

export const REDUCED_REVIEW = {
    marker: '<!-- eu-vat-reduced-rate-review -->',
    title: 'Default reduced VAT rates need review',
} as const;

export const SOURCE_FAILURE = {
    marker: '<!-- eu-vat-rate-source-failure -->',
    title: 'The VAT rate sources could not be read',
} as const;

export const ISSUE_LABELS = ['domain/checkout'];

export type OpenIssue = {
    number: number;
    title: string;
    body?: string | null;
};

export type IssuePlan =
    | { action: 'create' }
    | { action: 'update'; issueNumber: number }
    | { action: 'comment'; issueNumber: number }
    | { action: 'skip'; issueNumber: number };

export function fingerprintComment(fingerprint: string): string {
    return `<!-- fingerprint: ${fingerprint} -->`;
}

/**
 * `fingerprint` is omitted for the source-failure issue: every failure is worth a comment, because
 * the rates stay unvalidated until someone looks.
 */
export function planIssueAction(
    openIssues: OpenIssue[],
    options: { title: string; marker: string; fingerprint?: string },
): IssuePlan {
    const existing = openIssues.find(
        (issue) =>
            issue.title === options.title &&
            typeof issue.body === 'string' &&
            issue.body.includes(options.marker),
    );

    if (!existing) {
        return { action: 'create' };
    }

    if (options.fingerprint === undefined) {
        return { action: 'comment', issueNumber: existing.number };
    }

    if ((existing.body ?? '').includes(fingerprintComment(options.fingerprint))) {
        return { action: 'skip', issueNumber: existing.number };
    }

    return { action: 'update', issueNumber: existing.number };
}

export function buildStandardDriftBody(findings: string, fingerprint: string): string {
    return [
        STANDARD_DRIFT.marker,
        fingerprintComment(fingerprint),
        '',
        'The standard VAT rates in a freshly migrated database differ from the rates the European',
        'Commission (TEDB) and GOV.UK publish:',
        '',
        '```',
        findings,
        '```',
        '',
        'These are authoritative — a country has exactly one national standard rate, and all 27',
        'member states resolve unambiguously. Correct them with a new migration;',
        '`Migration1794989867CorrectCountryStandardVatRates` is the worked example, including the',
        'guards that leave a merchant-configured rate alone.',
        '',
        'Found by `.github/workflows/vat-rate-check.yml`.',
    ].join('\n');
}

export function buildReducedReviewBody(findings: string, fingerprint: string): string {
    return [
        REDUCED_REVIEW.marker,
        fingerprintComment(fingerprint),
        '',
        'The reduced VAT rates in a freshly migrated database differ from what TEDB reports:',
        '',
        '```',
        findings,
        '```',
        '',
        '**These are candidates, not verdicts.** TEDB cannot tell us which of a country\'s published',
        'rates belong in Shopware\'s two reduced slots: Belgium\'s 12%, Greece\'s 13% and Portugal\'s',
        '13% are each reported both as a parking rate and as a genuine reduced rate, while',
        'Luxembourg\'s 14% is reported the same way and is only the intermediate rate Shopware has',
        'never shipped. Every line needs a tax-domain judgement before it becomes a migration.',
        '',
        'Some countries also now levy three reduced rates — Austria added 4.9% on foodstuffs in July',
        '2026 — where `Migration1589359936AddTaxCountryRules` provides two slots bound to fixed tax',
        'classes, so correcting those needs a third tax class.',
        '',
        'Found by `.github/workflows/vat-rate-check.yml`.',
    ].join('\n');
}

export type Report = {
    title: string;
    marker: string;
    fingerprint?: string;
    body: string;
    changed: string;
};

/**
 * Decides what to report from the step outputs the check left behind. The field names are the step
 * output names the workflow exports, so this takes `process.env` straight from the step.
 */
export function buildReports(env: Record<string, string | undefined>, runUrl: string): Report[] {
    // Any status other than 1 means no verdict was formed, so the run itself is the finding.
    if (env.CHECK_STATUS !== '1') {
        return [
            {
                ...SOURCE_FAILURE,
                body: buildSourceFailureBody(runUrl),
                changed: `The check failed again: ${runUrl}`,
            },
        ];
    }

    const reports: Report[] = [];

    if (env.STANDARD_DRIFT) {
        const fingerprint = env.STANDARD_FINGERPRINT ?? '';
        reports.push({
            ...STANDARD_DRIFT,
            fingerprint,
            body: buildStandardDriftBody(env.STANDARD_DRIFT, fingerprint),
            changed: `The standard rate drift changed:\n\n\`\`\`\n${env.STANDARD_DRIFT}\n\`\`\``,
        });
    }

    if (env.REDUCED_CANDIDATES) {
        const fingerprint = env.REDUCED_FINGERPRINT ?? '';
        reports.push({
            ...REDUCED_REVIEW,
            fingerprint,
            body: buildReducedReviewBody(env.REDUCED_CANDIDATES, fingerprint),
            changed: `The reduced rate differences changed:\n\n\`\`\`\n${env.REDUCED_CANDIDATES}\n\`\`\``,
        });
    }

    return reports;
}

export function buildSourceFailureBody(runUrl: string): string {
    return [
        SOURCE_FAILURE.marker,
        '',
        'The monthly VAT rate check could not complete, so the default rates are currently',
        'unvalidated. Every stale source in this space answers HTTP 200, which is why a source',
        'failure is reported separately from a rate change instead of being retried away.',
        '',
        `See the run log for the reason: ${runUrl}`,
        '',
        'Found by `.github/workflows/vat-rate-check.yml`.',
    ].join('\n');
}

function log(message: string): void {
    process.stdout.write(`${message}\n`);
}

function setStepOutput(name: string, value: string): void {
    const target = process.env.GITHUB_OUTPUT;
    if (!target) {
        return;
    }

    const delimiter = `__vat_${name}_${Date.now()}__`;
    appendFileSync(target, `${name}<<${delimiter}\n${value}\n${delimiter}\n`);
}

function writeStepSummary(markdown: string): void {
    const target = process.env.GITHUB_STEP_SUMMARY;
    if (!target) {
        return;
    }

    appendFileSync(target, `${markdown}\n`);
}

function argument(name: string): string | null {
    const index = process.argv.indexOf(`--${name}`);
    if (index !== -1 && index + 1 < process.argv.length) {
        return process.argv[index + 1];
    }

    const inline = process.argv.find((candidate) => candidate.startsWith(`--${name}=`));

    return inline ? inline.slice(name.length + 3) : null;
}

async function fetchText(url: string, init: RequestInit, source: string): Promise<string> {
    let response: Response;

    try {
        response = await fetch(url, init);
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : String(error);

        throw new SourceError(`${source} is unreachable: ${message}`);
    }

    if (response.status !== 200) {
        throw new SourceError(`${source} answered with HTTP ${response.status}`);
    }

    return response.text();
}

async function fetchPublishedRates(date: string, queriedOn: Date): Promise<Map<string, CountryRates>> {
    const xml = await fetchText(
        TEDB_ENDPOINT,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'text/xml; charset=utf-8',
                SOAPAction: TEDB_SOAP_ACTION,
            },
            body: buildTedbEnvelope(MEMBER_STATES, date),
        },
        'TEDB',
    );

    const rates = parseTedbRates(xml, queriedOn);

    // A member state silently dropped from the response would otherwise read as "no change".
    const expected = MEMBER_STATES.map((iso) => ISO_OVERRIDES[iso] ?? iso);
    const missing = expected.filter((iso) => !rates.has(iso));
    if (missing.length > 0) {
        throw new SourceError(`TEDB reported no rates for ${missing.join(', ')}`);
    }

    const uk = await fetchText(GOVUK_ENDPOINT, { headers: { Accept: 'application/json' } }, 'GOV.UK');
    rates.set(UK_ISO, parseGovUkRates(uk));

    return rates;
}

function readCurrentDump(path: string): CurrentRateRow[] {
    let parsed: unknown;

    try {
        parsed = JSON.parse(readFileSync(path, 'utf8'));
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : String(error);

        throw new SourceError(`the database dump at "${path}" could not be read: ${message}`);
    }

    if (!Array.isArray(parsed)) {
        throw new SourceError(`the database dump at "${path}" is not a JSON array`);
    }

    return parsed as CurrentRateRow[];
}

function renderTable(diff: Diff): string {
    const lines: string[] = [];

    if (diff.standardDrift.length > 0) {
        lines.push('### Standard rate drift', '', '| Country | Database | Published |', '| --- | --- | --- |');
        for (const finding of diff.standardDrift) {
            lines.push(`| ${finding.iso} | ${finding.current} | **${finding.published}** |`);
        }
        lines.push('');
    }

    if (diff.reducedCandidates.length > 0) {
        lines.push('### Reduced rate differences (need review)', '', '| Country | Database | TEDB reports |', '| --- | --- | --- |');
        for (const finding of diff.reducedCandidates) {
            lines.push(`| ${finding.iso} | ${finding.current.join(', ') || '—'} | ${finding.published.join(', ') || '—'} |`);
        }
        lines.push('');
    }

    return lines.join('\n');
}

async function main(): Promise<void> {
    const dumpPath = argument('current');
    if (!dumpPath) {
        throw new SourceError('--current <path to the tax_rule dump> is required');
    }

    const date = argument('date') ?? new Date().toISOString().slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/u.test(date)) {
        throw new SourceError(`--date must be a YYYY-MM-DD date, got "${date}"`);
    }
    const queriedOn = new Date(`${date}T00:00:00Z`);

    const current = parseCurrentRates(readCurrentDump(dumpPath));

    // The 27 member states plus the UK. A short dump means the database was not fully migrated,
    // which must never read as "no drift".
    const expectedCountries = MEMBER_STATES.length + 1;
    if (current.size < expectedCountries) {
        throw new SourceError(
            `the database dump holds standard rates for ${current.size} countries, expected at least ${expectedCountries}`,
        );
    }

    const published = await fetchPublishedRates(date, queriedOn);
    const diff = diffRates(current, published);

    log(`Compared ${current.size} countries against the rates published for ${date}.`);

    setStepOutput('country_count', String(current.size));
    setStepOutput('standard_drift', diff.standardDrift.map((f) => `${f.iso}: ${f.current} -> ${f.published}`).join('\n'));
    setStepOutput('standard_fingerprint', fingerprint(diff.standardDrift));
    setStepOutput('reduced_candidates', diff.reducedCandidates.map((f) => `${f.iso}: ${f.current.join(', ') || '—'} -> ${f.published.join(', ') || '—'}`).join('\n'));
    setStepOutput('reduced_fingerprint', fingerprint(diff.reducedCandidates));

    if (diff.standardDrift.length === 0 && diff.reducedCandidates.length === 0) {
        log('Every default VAT rate matches the published rates.');
        writeStepSummary(`All ${current.size} countries match the rates published for ${date}.`);

        return;
    }

    const summary = renderTable(diff);
    log(summary);
    writeStepSummary(summary);

    log(
        `${diff.standardDrift.length} standard rate(s) drifted, ` +
            `${diff.reducedCandidates.length} country/countries differ in their reduced rates.`,
    );
    process.exit(1);
}

// Guarded, so the exports above stay importable from a test.
if (import.meta.url === `file://${process.argv[1]}`) {
    main().catch((error: unknown) => {
        const message = error instanceof Error ? error.message : String(error);

        // Never exit 1 here: automation must not mistake a broken source for a rate change.
        process.stderr.write(`::error::VAT rate check could not complete: ${message}\n`);
        process.exit(2);
    });
}
