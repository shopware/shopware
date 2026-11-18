import { appendFileSync, readFileSync } from 'node:fs';

export {
    FINDING_REPORTS,
    ISSUE_LABELS,
    SOURCE_FAILURE,
    STANDARD_TAX_CLASS,
    TEDB_MEMBER_STATES,
    SourceError,
    buildFindingBody,
    buildReports,
    buildSourceFailureBody,
    buildTedbEnvelope,
    canonicalRate,
    diffRates,
    fingerprint,
    fingerprintComment,
    formatFindings,
    isTerritorialComment,
    parseCurrentRates,
    parseGovUkRates,
    parseTedbRates,
    planIssueAction,
    sortRates,
    tedbRequestCodes,
    unvalidatedCountries,
    type Finding,
    type TrackedIssue,
};

const EXIT_CODE = { ratesMatch: 0, ratesDiffer: 1, sourceFailure: 2 } as const;
const ISO_DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/u;

const STANDARD_TAX_CLASS = 'Standard rate';
const MIN_COUNTRIES_WITH_STANDARD_RATE = 28;

const TEDB_SERVICE_NAMESPACE = 'urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService';
const MAX_TEDB_PERIOD_AGE_MONTHS = 24;
const TEDB_MEMBER_STATES = [
    'AT',
    'BE',
    'BG',
    'CY',
    'CZ',
    'DE',
    'DK',
    'EE',
    'EL',
    'ES',
    'FI',
    'FR',
    'HR',
    'HU',
    'IE',
    'IT',
    'LT',
    'LU',
    'LV',
    'MT',
    'NL',
    'PL',
    'PT',
    'RO',
    'SE',
    'SI',
    'SK',
];
const TEDB_CODE_BY_ISO = new Map(TEDB_MEMBER_STATES.map((code) => [isoFromTedbCode(code), code]));

const UK_ISO_CODE = 'GB';

const ISSUE_LABELS = ['domain/checkout'];
const ISSUE_FOOTER = 'Found by `.github/workflows/vat-rate-check.yml`.';

const SOURCE_FAILURE = {
    marker: '<!-- eu-vat-rate-source-failure -->',
    title: 'The VAT rate sources could not be read',
} as const;

const FINDING_REPORTS: Record<FindingKind, FindingReportTemplate> = {
    standard: {
        kind: 'standard',
        marker: '<!-- eu-vat-standard-rate-drift -->',
        title: 'Default standard VAT rates no longer match the published rates',
        heading: 'Standard rate drift',
        publishedColumn: 'Published',
        authoritative: true,
        findingsOutput: 'standard_drift',
        fingerprintOutput: 'standard_fingerprint',
        intro: [
            'The standard VAT rates in a freshly migrated database differ from the rates the European',
            'Commission (TEDB) and GOV.UK publish:',
        ],
        explanation: [
            'These are authoritative — a country has exactly one national standard rate, and all 27',
            'member states resolve unambiguously. Correct them with a new migration;',
            '`Migration1794852779UpdateDefaultTaxRules` is the worked example, including the',
            'guards that leave a merchant-configured rate alone.',
        ],
        changeNote: 'The standard rate drift changed',
    },
    reduced: {
        kind: 'reduced',
        marker: '<!-- eu-vat-reduced-rate-review -->',
        title: 'Default reduced VAT rates need review',
        heading: 'Reduced rate differences (need review)',
        publishedColumn: 'TEDB reports',
        authoritative: false,
        findingsOutput: 'reduced_candidates',
        fingerprintOutput: 'reduced_fingerprint',
        intro: ['The reduced VAT rates in a freshly migrated database differ from what TEDB reports:'],
        explanation: [
            "**These are candidates, not verdicts.** TEDB cannot tell us which of a country's published",
            "rates belong in Shopware's two reduced slots: Belgium's 12%, Greece's 13% and Portugal's",
            '13% are each reported both as a parking rate and as a genuine reduced rate, while',
            "Luxembourg's 14% is reported the same way and is only the intermediate rate Shopware has",
            'never shipped. Every line needs a tax-domain judgement before it becomes a migration.',
            '',
            'Some countries also now levy three reduced rates — Austria added 4.9% on foodstuffs in July',
            '2026 — where `Migration1589359936AddTaxCountryRules` provides two slots bound to fixed tax',
            'classes, so correcting those needs a third tax class.',
        ],
        changeNote: 'The reduced rate differences changed',
    },
};

type CurrentRateRow = {
    iso: string;
    taxClass: string;
    taxRate: number | string;
};

type CountryRates = {
    iso: string;
    standard: string;
    reduced: string[];
};

type RatesByCountry = Map<string, CountryRates>;

type FindingKind = 'standard' | 'reduced';

type TedbRateCandidates = Record<FindingKind, string[]>;

type Finding = {
    iso: string;
    current: string[];
    published: string[];
};

type Diff = Record<FindingKind, Finding[]>;

type FindingReportTemplate = {
    kind: FindingKind;
    marker: string;
    title: string;
    heading: string;
    publishedColumn: string;
    authoritative: boolean;
    findingsOutput: string;
    fingerprintOutput: string;
    intro: string[];
    explanation: string[];
    changeNote: string;
};

type Report = {
    title: string;
    marker: string;
    fingerprint?: string;
    body: string;
    changed: string;
};

type TrackedIssue = {
    number: number;
    title: string;
    state: 'open' | 'closed';
    body?: string | null;
};

type IssuePlan =
    | { action: 'create' }
    | { action: 'update'; issueNumber: number }
    | { action: 'comment'; issueNumber: number }
    | { action: 'skip'; issueNumber: number };

class SourceError extends Error {}

async function checkVatRates(): Promise<void> {
    const dumpPath = readCliOption('current');
    if (!dumpPath) {
        throw new SourceError('--current <path to the tax_rule dump> is required');
    }

    const queryDate = readQueryDate();
    const databaseRates = readDatabaseRates(dumpPath);
    const databaseCountries = [...databaseRates.keys()];
    const publishedRates = await fetchPublishedRates(databaseCountries, queryDate);
    const findings = diffRates(databaseRates, publishedRates);

    process.exitCode = reportComparison(queryDate, databaseCountries, publishedRates.size, findings);
}

function readCliOption(name: string): string | undefined {
    const option = `--${name}`;
    const optionIndex = process.argv.indexOf(option);
    if (optionIndex !== -1 && optionIndex + 1 < process.argv.length) {
        return process.argv[optionIndex + 1];
    }

    const prefix = `${option}=`;
    return process.argv.find((argument) => argument.startsWith(prefix))?.slice(prefix.length);
}

function readQueryDate(): string {
    const date = readCliOption('date') ?? formatIsoDate(new Date());

    if (!ISO_DATE_PATTERN.test(date)) {
        throw new SourceError(`--date must be a YYYY-MM-DD date, got "${date}"`);
    }

    return date;
}

function formatIsoDate(date: Date): string {
    return date.toISOString().slice(0, 10);
}

function errorMessage(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}

function canonicalRate(value: number | string): string {
    const numericRate = Number(value);

    if (!Number.isFinite(numericRate)) {
        throw new SourceError(`unreadable rate value "${String(value)}"`);
    }

    return String(Number(numericRate.toFixed(3)));
}

function sortRates(rates: string[]): string[] {
    return [...new Set(rates)].sort((left, right) => Number(left) - Number(right));
}

function requirePlausibleRate(rate: string, context: string): string {
    const percent = Number(rate);

    if (percent <= 0 || percent > 30) {
        throw new SourceError(`the VAT rate "${rate}" ${context} is outside the plausible range`);
    }

    return rate;
}

function readDatabaseRates(dumpPath: string): RatesByCountry {
    let rows: unknown;

    try {
        rows = JSON.parse(readFileSync(dumpPath, 'utf8'));
    } catch (error: unknown) {
        throw new SourceError(`the database dump at "${dumpPath}" could not be read: ${errorMessage(error)}`);
    }

    if (!Array.isArray(rows)) {
        throw new SourceError(`the database dump at "${dumpPath}" is not a JSON array`);
    }

    const rates = parseCurrentRates(rows);
    if (rates.size < MIN_COUNTRIES_WITH_STANDARD_RATE) {
        throw new SourceError(
            `the database dump holds standard rates for ${rates.size} countries, ` +
                `expected at least ${MIN_COUNTRIES_WITH_STANDARD_RATE}`,
        );
    }

    return rates;
}

function parseCurrentRates(rows: CurrentRateRow[]): RatesByCountry {
    const standardByCountry = new Map<string, string>();
    const reducedByCountry = new Map<string, string[]>();

    for (const row of rows) {
        const iso = row.iso.trim().toUpperCase();
        const rate = canonicalRate(row.taxRate);

        if (row.taxClass === STANDARD_TAX_CLASS) {
            standardByCountry.set(iso, rate);
        } else if (row.taxClass === 'Reduced rate' || row.taxClass === 'Reduced rate 2') {
            reducedByCountry.set(iso, [...(reducedByCountry.get(iso) ?? []), rate]);
        }
    }

    const rates = new Map<string, CountryRates>();

    for (const [iso, standard] of standardByCountry) {
        rates.set(iso, { iso, standard, reduced: sortRates(reducedByCountry.get(iso) ?? []) });
    }

    return rates;
}

async function fetchPublishedRates(isoCodes: string[], queryDate: string): Promise<RatesByCountry> {
    const rates = await fetchTedbRates(isoCodes, queryDate);

    if (isoCodes.includes(UK_ISO_CODE)) {
        rates.set(UK_ISO_CODE, await fetchGovUkRates());
    }

    return rates;
}

function unvalidatedCountries(isoCodes: string[]): string[] {
    return isoCodes.filter((iso) => !TEDB_CODE_BY_ISO.has(iso) && iso !== UK_ISO_CODE).sort();
}

async function fetchSourceText(source: string, url: string, options: RequestInit): Promise<string> {
    let response: Response;

    try {
        response = await fetch(url, options);
    } catch (error: unknown) {
        throw new SourceError(`${source} is unreachable: ${errorMessage(error)}`);
    }

    if (response.status !== 200) {
        throw new SourceError(`${source} answered with HTTP ${response.status}`);
    }

    return response.text();
}

function isoFromTedbCode(memberState: string): string {
    return memberState === 'EL' ? 'GR' : memberState;
}

function tedbRequestCodes(isoCodes: string[]): string[] {
    return isoCodes.map((iso) => TEDB_CODE_BY_ISO.get(iso)).filter((code) => code !== undefined);
}

async function fetchTedbRates(isoCodes: string[], queryDate: string): Promise<RatesByCountry> {
    const requestedMemberStates = tedbRequestCodes(isoCodes);
    if (requestedMemberStates.length === 0) {
        throw new SourceError('the database dump holds no country TEDB publishes a rate for');
    }

    const xml = await fetchSourceText('TEDB', 'https://ec.europa.eu/taxation_customs/tedb/ws/', {
        method: 'POST',
        headers: {
            'Content-Type': 'text/xml; charset=utf-8',
            SOAPAction: 'urn:ec.europa.eu:taxud:tedb:services:v1:VatRetrievalService/RetrieveVatRates',
        },
        body: buildTedbEnvelope(requestedMemberStates, queryDate),
    });

    const rates = parseTedbRates(xml, new Date(`${queryDate}T00:00:00Z`));
    const missingCountries = requestedMemberStates.map(isoFromTedbCode).filter((iso) => !rates.has(iso));
    if (missingCountries.length > 0) {
        throw new SourceError(`TEDB reported no rates for ${missingCountries.join(', ')}`);
    }

    return rates;
}

function buildTedbEnvelope(memberStateCodes: string[], queryDate: string): string {
    const memberStates = memberStateCodes.map((code) => `<t:isoCode>${code}</t:isoCode>`).join('');

    return (
        '<?xml version="1.0" encoding="UTF-8"?>' +
        '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' +
        `xmlns:svc="${TEDB_SERVICE_NAMESPACE}" xmlns:t="${TEDB_SERVICE_NAMESPACE}:types">` +
        '<soapenv:Body><svc:retrieveVatRatesReqMsg>' +
        `<t:memberStates>${memberStates}</t:memberStates><t:from>${queryDate}</t:from><t:to>${queryDate}</t:to>` +
        '</svc:retrieveVatRatesReqMsg></soapenv:Body></soapenv:Envelope>'
    );
}

function parseTedbRates(xml: string, queriedOn: Date): RatesByCountry {
    const candidatesByCountry = new Map<string, TedbRateCandidates>();
    let latestStartedPeriod: Date | null = null;

    for (const resultXml of readTedbRateResults(xml)) {
        const memberState = xmlElementContent(resultXml, 'memberState')?.toUpperCase();
        if (!memberState) {
            continue;
        }

        const periodStart = readTedbPeriodStart(resultXml);
        if (periodStart <= queriedOn && (latestStartedPeriod === null || periodStart > latestStartedPeriod)) {
            latestStartedPeriod = periodStart;
        }

        const rateXml = xmlElementContent(resultXml, 'rate') ?? '';
        const rateValue = xmlElementContent(rateXml, 'value');
        if (!rateValue) {
            continue;
        }

        const iso = isoFromTedbCode(memberState);
        const candidates = candidatesByCountry.get(iso) ?? { standard: [], reduced: [] };
        const kind = nationalTedbRateKind(xmlElementContent(rateXml, 'type'), resultXml);
        const rate = canonicalRate(rateValue);
        if (kind !== null) {
            candidates[kind].push(rate);
        }
        candidatesByCountry.set(iso, candidates);
    }

    assertRecentTedbPeriod(latestStartedPeriod, queriedOn);

    const rates = new Map<string, CountryRates>();
    for (const [iso, candidates] of candidatesByCountry) {
        rates.set(iso, resolveTedbCountryRates(iso, candidates));
    }

    return rates;
}

function readTedbRateResults(xml: string): string[] {
    const fault = /<(?:[\w.-]+:)?Fault\b[^>]*>[\s\S]*?<faultstring>([\s\S]*?)<\/faultstring>/i.exec(xml);
    if (fault) {
        throw new SourceError(`TEDB returned a SOAP fault: ${decodeXmlEntities(fault[1]).trim()}`);
    }

    const results = xml.match(/<vatRateResults\b[^>]*>[\s\S]*?<\/vatRateResults>/g);
    if (!results) {
        throw new SourceError('the TEDB response contains no vatRateResults');
    }

    return results;
}

function nationalTedbRateKind(rateType: string | undefined, resultXml: string): FindingKind | null {
    if (isTerritorialComment(xmlElementContent(resultXml, 'comment') ?? '')) {
        return null;
    }

    const hasGoodsCodes = /<(?:cnCodes|cpaCodes)[\s>]/u.test(resultXml);
    const hasCategory = /<category[\s>]/u.test(resultXml);

    if (rateType === 'DEFAULT' && !hasGoodsCodes && !hasCategory) {
        return 'standard';
    }

    if (rateType === 'SUPER_REDUCED_RATE' || (rateType === 'REDUCED_RATE' && hasGoodsCodes)) {
        return 'reduced';
    }

    return null;
}

function isTerritorialComment(comment: string): boolean {
    return /^VAT\s+-\s+.+?-\s*$/u.test(decodeXmlEntities(comment).trim());
}

function resolveTedbCountryRates(iso: string, candidates: TedbRateCandidates): CountryRates {
    const standardRates = sortRates(candidates.standard);
    if (standardRates.length === 0) {
        throw new SourceError(`TEDB reported no standard VAT rate for "${iso}"`);
    }

    if (standardRates.length > 1) {
        throw new SourceError(
            `TEDB reported more than one national standard VAT rate for "${iso}": ${standardRates.join(', ')}`,
        );
    }

    const standard = requirePlausibleRate(standardRates[0], `TEDB reports as the standard rate of "${iso}"`);
    const reduced = sortRates(candidates.reduced).filter((rate) => Number(rate) < Number(standard));

    return { iso, standard, reduced };
}

function readTedbPeriodStart(resultXml: string): Date {
    const startDate = (xmlElementContent(resultXml, 'situationOn') ?? '').slice(0, 10);
    const periodStart = new Date(`${startDate}T00:00:00Z`);

    if (!ISO_DATE_PATTERN.test(startDate) || Number.isNaN(periodStart.getTime())) {
        throw new SourceError(`unreadable situationOn value "${startDate}"`);
    }

    return periodStart;
}

function assertRecentTedbPeriod(latestStartedPeriod: Date | null, queriedOn: Date): void {
    if (latestStartedPeriod === null) {
        throw new SourceError(`no TEDB rate period had started on ${formatIsoDate(queriedOn)}`);
    }

    const oldestAllowedStart = new Date(queriedOn.getTime());
    oldestAllowedStart.setUTCMonth(oldestAllowedStart.getUTCMonth() - MAX_TEDB_PERIOD_AGE_MONTHS);

    if (latestStartedPeriod < oldestAllowedStart) {
        throw new SourceError(
            `TEDB looks frozen: its newest rate period starts ${formatIsoDate(latestStartedPeriod)}, ` +
                `which is more than ${MAX_TEDB_PERIOD_AGE_MONTHS} months before ${formatIsoDate(queriedOn)}`,
        );
    }
}

function xmlElementContent(xml: string, tagName: string): string | undefined {
    return new RegExp(`<${tagName}>([\\s\\S]*?)</${tagName}>`, 'u').exec(xml)?.[1].trim();
}

function decodeXmlEntities(text: string): string {
    return text
        .replace(/&lt;/gu, '<')
        .replace(/&gt;/gu, '>')
        .replace(/&quot;/gu, '"')
        .replace(/&apos;/gu, "'")
        .replace(/&#(\d+);/gu, (_, code: string) => String.fromCodePoint(Number(code)))
        .replace(/&amp;/gu, '&');
}

async function fetchGovUkRates(): Promise<CountryRates> {
    const payload = await fetchSourceText('GOV.UK', 'https://www.gov.uk/api/content/vat-rates', {
        headers: { Accept: 'application/json' },
    });

    return parseGovUkRates(payload);
}

function parseGovUkRates(payload: string): CountryRates {
    const { body, summary } = readGovUkPage(payload);
    const standardFromBody = readGovUkRate(
        body,
        /standard\s+VAT\s+rate\s+is\s*(?:<[^>]+>\s*)*(\d+(?:\.\d+)?)\s*%/iu,
        'the standard rate',
    );
    const standardFromSummary = readGovUkRate(summary, /standard\s+(\d+(?:\.\d+)?)\s*%/iu, 'the standard rate summary');

    if (standardFromBody !== standardFromSummary) {
        throw new SourceError(
            `the GOV.UK page reports the standard rate as both ${standardFromBody}% and ${standardFromSummary}%`,
        );
    }

    const reduced = readGovUkRate(body, /Reduced\s+rate[\s\S]*?(\d+(?:\.\d+)?)\s*%/iu, 'the reduced rate');

    return { iso: UK_ISO_CODE, standard: standardFromBody, reduced: [reduced] };
}

function readGovUkPage(payload: string): { body: string; summary: string } {
    let page: unknown;
    try {
        page = JSON.parse(payload);
    } catch {
        throw new SourceError('the GOV.UK response is not valid JSON');
    }

    if (typeof page !== 'object' || page === null || Array.isArray(page)) {
        throw new SourceError('the GOV.UK response is not a JSON object');
    }

    const { description, details } = page as { description?: unknown; details?: { body?: unknown } };
    if (typeof details?.body !== 'string' || typeof description !== 'string') {
        throw new SourceError('the GOV.UK response carries no rate description');
    }

    return { body: details.body, summary: description };
}

function readGovUkRate(text: string, pattern: RegExp, rateLabel: string): string {
    const match = pattern.exec(text);
    if (!match) {
        throw new SourceError(`${rateLabel} could not be read from the GOV.UK page`);
    }

    return requirePlausibleRate(canonicalRate(match[1]), `the GOV.UK page reports as ${rateLabel}`);
}

function diffRates(databaseRates: RatesByCountry, publishedRates: RatesByCountry): Diff {
    const findings: Diff = { standard: [], reduced: [] };

    for (const iso of [...publishedRates.keys()].sort()) {
        const current = databaseRates.get(iso);
        const published = publishedRates.get(iso);
        if (!current || !published) {
            continue;
        }

        if (current.standard !== published.standard) {
            findings.standard.push({ iso, current: [current.standard], published: [published.standard] });
        }

        if (current.reduced.join(',') !== published.reduced.join(',')) {
            findings.reduced.push({ iso, current: current.reduced, published: published.reduced });
        }
    }

    return findings;
}

function fingerprint(findings: Finding[]): string {
    return findings
        .map((finding) => `${finding.iso}:${finding.current.join('/')}>${finding.published.join('/')}`)
        .sort()
        .join(' ');
}

function reportComparison(
    queryDate: string,
    databaseCountries: string[],
    checkedCountryCount: number,
    findings: Diff,
): number {
    const uncheckedCountries = unvalidatedCountries(databaseCountries);
    console.log(
        `Compared ${checkedCountryCount} of ${databaseCountries.length} countries against the rates published for ${queryDate}.`,
    );
    if (uncheckedCountries.length > 0) {
        console.log(`No source publishes a rate for ${uncheckedCountries.join(', ')}, so those rates were not checked.`);
    }

    setStepOutput('country_count', String(databaseCountries.length));
    writeFindingOutputs(findings);

    const coverageNote =
        uncheckedCountries.length > 0 ? `\n\nNo source publishes a rate for ${uncheckedCountries.join(', ')}.` : '';
    if (findings.standard.length === 0 && findings.reduced.length === 0) {
        console.log('Every default VAT rate matches the published rates.');
        writeStepSummary(
            `All ${checkedCountryCount} checked countries match the rates published for ${queryDate}.${coverageNote}`,
        );
        return EXIT_CODE.ratesMatch;
    }

    writeStepSummary(`${renderStepSummary(findings)}${coverageNote}`);
    console.log(
        `${findings.standard.length} standard rate(s) drifted, ` +
            `${findings.reduced.length} country/countries differ in their reduced rates.`,
    );
    return EXIT_CODE.ratesDiffer;
}

function writeFindingOutputs(findingsByKind: Diff): void {
    for (const template of Object.values(FINDING_REPORTS)) {
        const findings = findingsByKind[template.kind];
        const table = formatFindings(template, findings);
        setStepOutput(template.findingsOutput, table);
        setStepOutput(template.fingerprintOutput, fingerprint(findings));

        if (findings.length > 0) {
            console.log(`\n${template.heading}\n${table}`);
        }
    }
}

function formatFindings(template: FindingReportTemplate, findings: Finding[]): string {
    if (findings.length === 0) {
        return '';
    }

    return [
        ['Country', 'Current', template.publishedColumn],
        ...findings.map((finding) => [finding.iso, formatRateList(finding.current), formatRateList(finding.published)]),
    ]
        .map((cells) => cells.join('\t'))
        .join('\n');
}

function renderStepSummary(findingsByKind: Diff): string {
    const lines: string[] = [];

    for (const template of Object.values(FINDING_REPORTS)) {
        const findings = findingsByKind[template.kind];
        if (findings.length === 0) {
            continue;
        }

        lines.push(
            `### ${template.heading}`,
            '',
            `| Country | Database | ${template.publishedColumn} |`,
            '| --- | --- | --- |',
        );

        for (const finding of findings) {
            const published = formatRateList(finding.published);
            const publishedCell = template.authoritative ? `**${published}**` : published;

            lines.push(`| ${finding.iso} | ${formatRateList(finding.current)} | ${publishedCell} |`);
        }

        lines.push('');
    }

    return lines.join('\n');
}

function formatRateList(rates: string[]): string {
    return rates.join(', ') || '—';
}

function setStepOutput(name: string, value: string): void {
    const outputPath = process.env.GITHUB_OUTPUT;
    if (!outputPath) {
        return;
    }

    const delimiter = `__vat_${name}_${Date.now()}__`;
    appendFileSync(outputPath, `${name}<<${delimiter}\n${value}\n${delimiter}\n`);
}

function writeStepSummary(markdown: string): void {
    const summaryPath = process.env.GITHUB_STEP_SUMMARY;
    if (summaryPath) {
        appendFileSync(summaryPath, `${markdown}\n`);
    }
}

function buildReports(env: Record<string, string | undefined>, runUrl: string): Report[] {
    if (env.CHECK_STATUS !== String(EXIT_CODE.ratesDiffer)) {
        return [
            {
                ...SOURCE_FAILURE,
                body: buildSourceFailureBody(runUrl),
                changed: `The check failed again: ${runUrl}`,
            },
        ];
    }

    const reports: Report[] = [];
    for (const template of Object.values(FINDING_REPORTS)) {
        const findingTable = env[template.findingsOutput.toUpperCase()];
        if (!findingTable) {
            continue;
        }

        const findingFingerprint = env[template.fingerprintOutput.toUpperCase()] ?? '';
        reports.push({
            title: template.title,
            marker: template.marker,
            fingerprint: findingFingerprint,
            body: buildFindingBody(template, findingTable, findingFingerprint),
            changed: `${template.changeNote}:\n\n${fencedCodeBlock(findingTable)}`,
        });
    }

    return reports;
}

function buildFindingBody(template: FindingReportTemplate, findingTable: string, findingFingerprint: string): string {
    return [
        template.marker,
        fingerprintComment(findingFingerprint),
        '',
        ...template.intro,
        '',
        fencedCodeBlock(findingTable),
        '',
        ...template.explanation,
        '',
        ISSUE_FOOTER,
    ].join('\n');
}

function fencedCodeBlock(text: string): string {
    return ['```', text, '```'].join('\n');
}

function buildSourceFailureBody(runUrl: string): string {
    return [
        SOURCE_FAILURE.marker,
        '',
        'The monthly VAT rate check could not complete, so the default rates are currently',
        'unvalidated. Every stale source in this space answers HTTP 200, which is why a source',
        'failure is reported separately from a rate change instead of being retried away.',
        '',
        `See the run log for the reason: ${runUrl}`,
        '',
        ISSUE_FOOTER,
    ].join('\n');
}

function fingerprintComment(findingFingerprint: string): string {
    return `<!-- fingerprint: ${findingFingerprint} -->`;
}

function planIssueAction(issues: TrackedIssue[], report: Pick<Report, 'title' | 'marker' | 'fingerprint'>): IssuePlan {
    const matchingIssues = issues.filter(
        (issue) => issue.title === report.title && typeof issue.body === 'string' && issue.body.includes(report.marker),
    );

    if (report.fingerprint !== undefined) {
        const fingerprintMarker = fingerprintComment(report.fingerprint);
        const issueWithSameFindings = matchingIssues.find((issue) => issue.body?.includes(fingerprintMarker));
        if (issueWithSameFindings) {
            return { action: 'skip', issueNumber: issueWithSameFindings.number };
        }
    }

    const openIssue = matchingIssues.find((issue) => issue.state === 'open');
    if (!openIssue) {
        return { action: 'create' };
    }

    return { action: report.fingerprint === undefined ? 'comment' : 'update', issueNumber: openIssue.number };
}

if (import.meta.url === `file://${process.argv[1]}`) {
    checkVatRates().catch((error: unknown) => {
        process.stderr.write(`::error::VAT rate check could not complete: ${errorMessage(error)}\n`);
        process.exitCode = EXIT_CODE.sourceFailure;
    });
}
