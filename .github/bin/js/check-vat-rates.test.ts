import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    FINDING_REPORTS,
    TEDB_MEMBER_STATES,
    SOURCE_FAILURE,
    SourceError,
    STANDARD_TAX_CLASS,
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
} from './check-vat-rates.ts';

const QUERIED_ON = new Date('2026-11-18T00:00:00Z');
const PERIOD = '2026-07-01+02:00';

/**
 * The entry shapes below are taken verbatim from a real TEDB response captured on 2026-11-18, with
 * the long goods-code and category description lists cut down — the parser only reads whether those
 * elements are present.
 */
function entry(options: {
    iso: string;
    type: string;
    value?: string;
    comment?: string;
    category?: string;
    codes?: 'cn' | 'cpa';
    code?: string;
    situationOn?: string;
}): string {
    const value = options.value === undefined ? '' : `<value>${options.value}</value>`;
    const codes =
        options.codes === undefined
            ? ''
            : `<${options.codes}Codes><code><value>${options.code ?? '0201'}</value></code></${options.codes}Codes>`;
    const category = options.category ? `<category><identifier>${options.category}</identifier></category>` : '';
    const comment = options.comment === undefined ? '' : `<comment>${options.comment}</comment>`;

    return (
        `<vatRateResults><memberState>${options.iso}</memberState>` +
        `<rate><type>${options.type}</type>${value}</rate>` +
        `<situationOn>${options.situationOn ?? PERIOD}</situationOn>${codes}${category}${comment}` +
        '</vatRateResults>'
    );
}

function envelope(body: string): string {
    return (
        '<?xml version="1.0" encoding="UTF-8"?>' +
        '<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body>' +
        '<ns0:retrieveVatRatesRespMsg' +
        ' xmlns="urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService:types"' +
        ' xmlns:ns0="urn:ec.europa.eu:taxud:tedb:services:v1:IVatRetrievalService">' +
        `${body}</ns0:retrieveVatRatesRespMsg></env:Body></env:Envelope>`
    );
}

function parse(body: string, queriedOn = QUERIED_ON) {
    return parseTedbRates(envelope(body), queriedOn);
}

test('canonicalRate renders the shortest string that preserves the rate', () => {
    assert.equal(canonicalRate('19.0'), '19');
    assert.equal(canonicalRate(19), '19');
    assert.equal(canonicalRate('19.000'), '19');
    assert.equal(canonicalRate('9.50'), '9.5');
    assert.equal(canonicalRate(25.5), '25.5');
    assert.equal(canonicalRate('4.9'), '4.9');
    assert.equal(canonicalRate('13.5'), '13.5');
    assert.equal(canonicalRate(0), '0');
    assert.throws(() => canonicalRate('twenty'), SourceError);
});

test('sortRates orders by value, not by character, and deduplicates', () => {
    assert.deepEqual(sortRates(['13', '9.5', '10']), ['9.5', '10', '13']);
    assert.deepEqual(sortRates(['12', '12', '6']), ['6', '12']);
    assert.deepEqual(sortRates([]), []);
});

test('isTerritorialComment tells a territorial marker from a legal citation', () => {
    assert.equal(isTerritorialComment('VAT - Canary Islands - '), true);
    assert.equal(isTerritorialComment('VAT - Import - '), true);
    // BE, CZ, FR, IE, LU and LV annotate their national standard rate this way.
    assert.equal(isTerritorialComment('&lt;p&gt;Art. 47 of the VAT Act.&lt;/p&gt;'), false);
    assert.equal(isTerritorialComment('Jungholz, Mittelberg'), false);
    assert.equal(isTerritorialComment('-'), false);
});

test('the request carries the split namespaces and the non-deprecated period fields', () => {
    const body = buildTedbEnvelope(['DE', 'EL'], '2026-11-18');

    assert.match(body, /xmlns:svc="urn:ec\.europa\.eu:taxud:tedb:services:v1:IVatRetrievalService"/u);
    assert.match(body, /xmlns:t="urn:ec\.europa\.eu:taxud:tedb:services:v1:IVatRetrievalService:types"/u);
    assert.match(body, /<svc:retrieveVatRatesReqMsg>/u);
    assert.match(body, /<t:memberStates><t:isoCode>DE<\/t:isoCode><t:isoCode>EL<\/t:isoCode><\/t:memberStates>/u);
    // The schema marks situationOn deprecated.
    assert.match(body, /<t:from>2026-11-18<\/t:from><t:to>2026-11-18<\/t:to>/u);
    assert.doesNotMatch(body, /situationOn/u);

    // Requested under the code TEDB uses for Greece, not the ISO code.
    assert.equal(TEDB_MEMBER_STATES.length, 27);
    assert.ok(TEDB_MEMBER_STATES.includes('EL') && !TEDB_MEMBER_STATES.includes('GR'));
});

test('Spain takes the national standard rate over the Canary Islands rate', () => {
    // Both entries look unqualified; only the comment separates them.
    const rates = parse(
        entry({ iso: 'ES', type: 'DEFAULT', value: '7.0', comment: 'VAT - Canary Islands - ' }) +
            entry({ iso: 'ES', type: 'DEFAULT', value: '21.0' }),
    );

    assert.equal(rates.get('ES')?.standard, '21');
});

test('Germany drops the duplicate import entry', () => {
    const rates = parse(
        entry({ iso: 'DE', type: 'DEFAULT', value: '19.0', comment: 'VAT - Import - ' }) +
            entry({ iso: 'DE', type: 'DEFAULT', value: '19.0' }) +
            entry({ iso: 'DE', type: 'REDUCED_RATE', value: '7.0', codes: 'cn' }),
    );

    assert.equal(rates.get('DE')?.standard, '19');
    assert.deepEqual(rates.get('DE')?.reduced, ['7']);
});

test('a standard rate annotated with a legal citation is kept', () => {
    const rates = parse(
        entry({
            iso: 'BE',
            type: 'DEFAULT',
            value: '21.0',
            comment: '&lt;p&gt;Article 1, paragraph 1, of the Royal Decree n° 20&lt;/p&gt;',
        }),
    );

    assert.equal(rates.get('BE')?.standard, '21');
});

test('Greece is reported under its ISO code', () => {
    const rates = parse(entry({ iso: 'EL', type: 'DEFAULT', value: '24.0' }));

    assert.equal(rates.get('GR')?.standard, '24');
    assert.equal(rates.has('EL'), false);
});

test('a reduced rate that applies to one region only is excluded', () => {
    // Austria's 19% (Jungholz), Greece's 17% (Aegean) and Portugal's 16%/22% (Azores, Madeira) are
    // all REGION-category entries without goods codes.
    const rates = parse(
        entry({ iso: 'AT', type: 'DEFAULT', value: '20.0' }) +
            entry({ iso: 'AT', type: 'REDUCED_RATE', value: '19.0', category: 'REGION', comment: 'Jungholz, Mittelberg' }) +
            entry({ iso: 'AT', type: 'REDUCED_RATE', value: '13.0', codes: 'cn', category: 'PLANT' }) +
            entry({ iso: 'AT', type: 'REDUCED_RATE', value: '10.0', codes: 'cn', category: 'FOODSTUFFS' }),
    );

    assert.deepEqual(rates.get('AT')?.reduced, ['10', '13']);
});

test('a super-reduced rate carrying only a category is kept', () => {
    // Spain's 4% sits on a category entry with no goods codes. Requiring codes would lose it, and
    // Shopware has always shipped it as an ordinary reduced rate.
    const rates = parse(
        entry({ iso: 'ES', type: 'DEFAULT', value: '21.0' }) +
            entry({ iso: 'ES', type: 'REDUCED_RATE', value: '10.0', codes: 'cn', category: 'FOODSTUFFS' }) +
            entry({ iso: 'ES', type: 'SUPER_REDUCED_RATE', value: '4.0', category: 'EXEMPTION_SUPERREDUCED', comment: '-' }),
    );

    assert.deepEqual(rates.get('ES')?.reduced, ['4', '10']);
});

test('a parking rate is excluded by its rate type', () => {
    const rates = parse(
        entry({ iso: 'LU', type: 'DEFAULT', value: '17.0' }) +
            entry({ iso: 'LU', type: 'PARKING_RATE', value: '14.0', codes: 'cn', category: 'NEW_PARKING_RATE' }) +
            entry({ iso: 'LU', type: 'SUPER_REDUCED_RATE', value: '3.0', codes: 'cn', category: 'FOODSTUFFS' }),
    );

    assert.deepEqual(rates.get('LU')?.reduced, ['3']);
});

test('a rate that is both a parking rate and a reduced rate stays — the known limitation', () => {
    // Belgium's 12%, Greece's 13% and Portugal's 13% are reported both ways and are genuine reduced
    // rates; Luxembourg's 14% is reported both ways and is not. Nothing in the payload separates
    // them, which is why a reduced-rate difference is a review candidate and never a correction.
    const rates = parse(
        entry({ iso: 'BE', type: 'DEFAULT', value: '21.0' }) +
            entry({ iso: 'BE', type: 'PARKING_RATE', value: '12.0', codes: 'cn', category: 'NEW_PARKING_RATE' }) +
            entry({ iso: 'BE', type: 'REDUCED_RATE', value: '12.0', codes: 'cn', category: 'COAL' }) +
            entry({ iso: 'BE', type: 'REDUCED_RATE', value: '6.0', codes: 'cn', category: 'PLANT' }),
    );

    assert.deepEqual(rates.get('BE')?.reduced, ['6', '12']);
});

test('a reduced rate at or above the standard rate is dropped', () => {
    const rates = parse(
        entry({ iso: 'DE', type: 'DEFAULT', value: '19.0' }) +
            entry({ iso: 'DE', type: 'REDUCED_RATE', value: '19.0', codes: 'cn' }) +
            entry({ iso: 'DE', type: 'REDUCED_RATE', value: '21.0', codes: 'cn' }) +
            entry({ iso: 'DE', type: 'REDUCED_RATE', value: '7.0', codes: 'cn' }),
    );

    assert.deepEqual(rates.get('DE')?.reduced, ['7']);
});

test('an entry without a rate value is skipped', () => {
    // NOT_APPLICABLE, OUT_OF_SCOPE and EXEMPTED entries carry no value element.
    const rates = parse(
        entry({ iso: 'DE', type: 'NOT_APPLICABLE' }) +
            entry({ iso: 'DE', type: 'EXEMPTED' }) +
            entry({ iso: 'DE', type: 'DEFAULT', value: '19.0' }),
    );

    assert.equal(rates.get('DE')?.standard, '19');
    assert.deepEqual(rates.get('DE')?.reduced, []);
});

test('a goods code is never mistaken for the rate value', () => {
    // The `<rate>` element carries no `<value>`, but the entry carries a CN code of 0004. Reading
    // the value across the whole entry picks that code up as a 4% reduced rate, and 4% clears both
    // guards — it is in the plausible range and below the 19% standard rate.
    const rates = parse(
        entry({ iso: 'DE', type: 'DEFAULT', value: '19.0' }) +
            entry({ iso: 'DE', type: 'REDUCED_RATE', codes: 'cn', code: '0004' }),
    );

    assert.deepEqual(rates.get('DE')?.reduced, []);
});

test('a SOAP fault is a source failure', () => {
    const fault =
        '<?xml version="1.0" encoding="UTF-8"?>' +
        '<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body>' +
        '<env:Fault><faultstring>TEDB-ERR-2 - Request is not valid</faultstring></env:Fault>' +
        '</env:Body></env:Envelope>';

    assert.throws(() => parseTedbRates(fault, QUERIED_ON), /TEDB-ERR-2/u);
});

test('a response without any rate entries is a source failure', () => {
    assert.throws(() => parse(''), /no vatRateResults/u);
});

test('two unqualified standard rates for one country are a source failure', () => {
    assert.throws(
        () =>
            parse(
                entry({ iso: 'ES', type: 'DEFAULT', value: '7.0' }) + entry({ iso: 'ES', type: 'DEFAULT', value: '21.0' }),
            ),
        /more than one national standard VAT rate for "ES"/u,
    );
});

test('a country without a standard rate is a source failure', () => {
    assert.throws(
        () => parse(entry({ iso: 'DE', type: 'REDUCED_RATE', value: '7.0', codes: 'cn' })),
        /no standard VAT rate for "DE"/u,
    );
});

test('an implausible standard rate is a source failure', () => {
    assert.throws(() => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '42.0' })), /plausible range/u);
    assert.throws(() => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '1900.0' })), /plausible range/u);
    assert.throws(() => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '0.0' })), /plausible range/u);
});

test('a frozen record is a source failure', () => {
    // How TEDB's pre-Brexit UK record gives itself away: HTTP 200, a 2020-01-01 period start.
    assert.throws(
        () => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '19.0', situationOn: '2020-01-01+01:00' })),
        /looks frozen/u,
    );
});

test('an announced future period does not count as the newest period', () => {
    assert.throws(
        () => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '19.0', situationOn: '2027-01-01+01:00' })),
        /no TEDB rate period had started/u,
    );
});

test('an unreadable period start is a source failure', () => {
    assert.throws(
        () => parse(entry({ iso: 'DE', type: 'DEFAULT', value: '19.0', situationOn: 'not-a-date' })),
        /unreadable situationOn/u,
    );
});

test('GOV.UK yields the standard and reduced rate and ignores the historical one', () => {
    const page = JSON.stringify({
        description: 'Current VAT rates - standard 20% and rates for reduced rate and zero-rated items',
        details: {
            body:
                '<div class="highlight-answer"><p>The standard VAT rate is <em>20%</em></p></div>' +
                '<tr><th scope="row">Standard rate</th><td>20%</td></tr>' +
                '<tr><th scope="row">Reduced rate</th><td>5%</td></tr>' +
                '<tr><th scope="row">Zero rate</th><td>0%</td></tr>' +
                '<p>The standard rate of VAT increased to 20% on 4 January 2011 (from 17.5%).</p>',
        },
    });

    const rates = parseGovUkRates(page);

    assert.equal(rates.iso, 'GB');
    assert.equal(rates.standard, '20');
    assert.deepEqual(rates.reduced, ['5']);
    assert.ok(!rates.reduced.includes('17.5'));
    assert.ok(!rates.reduced.includes('0'));
});

test('a GOV.UK page that contradicts itself is a source failure', () => {
    const page = JSON.stringify({
        description: 'Current VAT rates - standard 17.5%',
        details: { body: '<p>The standard VAT rate is <em>20%</em></p><tr><th>Reduced rate</th><td>5%</td></tr>' },
    });

    assert.throws(() => parseGovUkRates(page), /both 20% and 17\.5%/u);
});

test('an unusable GOV.UK response is a source failure', () => {
    assert.throws(() => parseGovUkRates('<html>maintenance</html>'), /not valid JSON/u);
    assert.throws(() => parseGovUkRates('"gone"'), /not a JSON object/u);
    assert.throws(() => parseGovUkRates('[]'), /not a JSON object/u);
    assert.throws(() => parseGovUkRates('{"description":"standard 20%"}'), /no rate description/u);
});

test('what gets requested follows the database, translated into TEDB codes', () => {
    // Greece is held as GR and has to go out as EL, or TEDB answers for 26 of the 27.
    assert.deepEqual(tedbRequestCodes(['DE', 'GR', 'FR']), ['DE', 'EL', 'FR']);
    // A country TEDB does not publish must not be requested — it would fail the whole run.
    assert.deepEqual(
        tedbRequestCodes([
            'DE',
            'CH',
            'GB',
            'NO',
        ]),
        ['DE'],
    );
    assert.deepEqual(tedbRequestCodes([]), []);
});

test('a country no source publishes is named, not silently skipped', () => {
    // The hole a hardcoded country list left: an ISO added to the database went unchecked for good.
    assert.deepEqual(
        unvalidatedCountries([
            'DE',
            'CH',
            'GR',
            'NO',
        ]),
        ['CH', 'NO'],
    );
    // TEDB covers the member states, GOV.UK covers the UK, so neither is unvalidated.
    assert.deepEqual(unvalidatedCountries(['DE', 'GR', 'GB']), []);
    assert.deepEqual(unvalidatedCountries([]), []);
});

test('the database dump is folded into one entry per country', () => {
    const rates = parseCurrentRates([
        { iso: 'DE', taxClass: STANDARD_TAX_CLASS, taxRate: 19 },
        { iso: 'DE', taxClass: 'Reduced rate', taxRate: 7 },
        { iso: 'FI', taxClass: STANDARD_TAX_CLASS, taxRate: 25.5 },
        { iso: 'FI', taxClass: 'Reduced rate', taxRate: 14 },
        { iso: 'FI', taxClass: 'Reduced rate 2', taxRate: 10 },
    ]);

    assert.equal(rates.get('DE')?.standard, '19');
    assert.deepEqual(rates.get('DE')?.reduced, ['7']);
    assert.equal(rates.get('FI')?.standard, '25.5');
    assert.deepEqual(rates.get('FI')?.reduced, ['10', '14']);
});

test('a country without a standard rate row is skipped, not reported as zero', () => {
    // Migration1589359936AddTaxCountryRules bails out entirely on a shop whose taxes were touched,
    // so a missing row is a legitimate state rather than drift.
    const rates = parseCurrentRates([{ iso: 'XX', taxClass: 'Reduced rate', taxRate: 5 }]);

    assert.equal(rates.has('XX'), false);
});

test('a tax class the migration never wrote is ignored', () => {
    const rates = parseCurrentRates([
        { iso: 'DE', taxClass: STANDARD_TAX_CLASS, taxRate: 19 },
        { iso: 'DE', taxClass: 'My own class', taxRate: 11 },
    ]);

    assert.deepEqual(rates.get('DE')?.reduced, []);
});

test('matching rates produce no findings', () => {
    const rows = [
        { iso: 'DE', taxClass: STANDARD_TAX_CLASS, taxRate: 19 },
        { iso: 'DE', taxClass: 'Reduced rate', taxRate: 7 },
    ];
    const published = new Map([['DE', { iso: 'DE', standard: '19', reduced: ['7'] }]]);

    const diff = diffRates(parseCurrentRates(rows), published);

    assert.deepEqual(diff.standard, []);
    assert.deepEqual(diff.reduced, []);
});

test('a drifted standard rate is reported, and separately from reduced differences', () => {
    const current = parseCurrentRates([
        { iso: 'EE', taxClass: STANDARD_TAX_CLASS, taxRate: 20 },
        { iso: 'EE', taxClass: 'Reduced rate', taxRate: 9 },
        { iso: 'RO', taxClass: STANDARD_TAX_CLASS, taxRate: 21 },
        { iso: 'RO', taxClass: 'Reduced rate', taxRate: 9 },
        { iso: 'RO', taxClass: 'Reduced rate 2', taxRate: 5 },
    ]);
    const published = new Map([
        ['EE', { iso: 'EE', standard: '24', reduced: ['9'] }],
        ['RO', { iso: 'RO', standard: '21', reduced: ['11'] }],
    ]);

    const diff = diffRates(current, published);

    assert.deepEqual(diff.standard, [{ iso: 'EE', current: ['20'], published: ['24'] }]);
    assert.deepEqual(diff.reduced, [{ iso: 'RO', current: ['5', '9'], published: ['11'] }]);
});

test('findings are ordered by ISO code so the report does not depend on source order', () => {
    const current = parseCurrentRates([
        { iso: 'SK', taxClass: STANDARD_TAX_CLASS, taxRate: 20 },
        { iso: 'EE', taxClass: STANDARD_TAX_CLASS, taxRate: 20 },
        { iso: 'FI', taxClass: STANDARD_TAX_CLASS, taxRate: 24 },
    ]);
    const published = new Map([
        ['SK', { iso: 'SK', standard: '23', reduced: [] }],
        ['FI', { iso: 'FI', standard: '25.5', reduced: [] }],
        ['EE', { iso: 'EE', standard: '24', reduced: [] }],
    ]);

    assert.deepEqual(
        diffRates(current, published).standard.map((finding) => finding.iso),
        ['EE', 'FI', 'SK'],
    );
});

test('a country missing from the database is not reported as drift', () => {
    const current = parseCurrentRates([{ iso: 'DE', taxClass: STANDARD_TAX_CLASS, taxRate: 19 }]);
    const published = new Map([
        ['DE', { iso: 'DE', standard: '19', reduced: [] }],
        ['HR', { iso: 'HR', standard: '25', reduced: [] }],
    ]);

    const diff = diffRates(current, published);

    assert.deepEqual(diff.standard, []);
    assert.deepEqual(diff.reduced, []);
});

test('the fingerprint is stable across ordering and changes with the finding', () => {
    const one: Finding[] = [
        { iso: 'FI', current: ['24'], published: ['25.5'] },
        { iso: 'EE', current: ['20'], published: ['24'] },
    ];
    const same: Finding[] = [
        { iso: 'EE', current: ['20'], published: ['24'] },
        { iso: 'FI', current: ['24'], published: ['25.5'] },
    ];

    assert.equal(fingerprint(one), fingerprint(same));
    assert.notEqual(fingerprint(one), fingerprint([{ iso: 'EE', current: ['20'], published: ['24'] }]));
    assert.equal(fingerprint([]), '');
    assert.equal(fingerprint([{ iso: 'RO', current: ['5', '9'], published: ['11'] }]), 'RO:5/9>11');
});

// ── Reporting ────────────────────────────────────────────────────────────────────────────────

const DRIFT = formatFindings(FINDING_REPORTS.standard, [
    { iso: 'EE', current: ['20'], published: ['24'] },
    { iso: 'FI', current: ['24'], published: ['25.5'] },
]);
const REDUCED = formatFindings(FINDING_REPORTS.reduced, [{ iso: 'RO', current: ['5', '9'], published: ['11'] }]);
const FINGERPRINT = 'EE:20>24 FI:24>25.5';

const RUN_URL = 'https://example.test/run/1';

// Line-exact, because asserting a bare URL with `includes` reads as a URL check to code scanners.
function rendersRunUrl(body: string, runUrl: string): boolean {
    return body.split('\n').some((line) => line === `See the run log for the reason: ${runUrl}`);
}

function issue(overrides: Partial<TrackedIssue> = {}): TrackedIssue {
    return {
        number: 42,
        title: FINDING_REPORTS.standard.title,
        state: 'open',
        body: `${FINDING_REPORTS.standard.marker}\n${fingerprintComment(FINGERPRINT)}\n\nbody`,
        ...overrides,
    };
}

test('with no open issue, one is created', () => {
    assert.deepEqual(planIssueAction([], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
        action: 'create',
    });
});

test('an unchanged finding stays quiet instead of commenting every month', () => {
    assert.deepEqual(planIssueAction([issue()], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
        action: 'skip',
        issueNumber: 42,
    });
});

test('a changed finding updates the existing issue', () => {
    const plan = planIssueAction([issue()], { ...FINDING_REPORTS.standard, fingerprint: 'EE:20>24' });

    assert.deepEqual(plan, { action: 'update', issueNumber: 42 });
});

test('an issue is matched on title and marker together, never on one alone', () => {
    // Someone else's issue with the same title must not be hijacked, and the marker alone must not
    // pull in an unrelated issue.
    for (const mismatch of [issue({ body: 'no marker here' }), issue({ title: 'Something else' })]) {
        assert.deepEqual(planIssueAction([mismatch], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
            action: 'create',
        });
    }
});

test('an issue without a body is not mistaken for a match', () => {
    assert.deepEqual(planIssueAction([issue({ body: null })], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
        action: 'create',
    });
});

test('the standard-drift and reduced-review issues never collide', () => {
    const standard = issue();
    const plan = planIssueAction([standard], { ...FINDING_REPORTS.reduced, fingerprint: 'RO:5/9>11' });

    assert.deepEqual(plan, { action: 'create' });
    assert.notEqual(FINDING_REPORTS.standard.marker, FINDING_REPORTS.reduced.marker);
    assert.notEqual(FINDING_REPORTS.standard.title, FINDING_REPORTS.reduced.title);
});

test('without a fingerprint every occurrence is worth a comment', () => {
    // The source-failure issue works this way: the rates stay unvalidated until someone looks.
    const existing = issue({ title: SOURCE_FAILURE.title, body: SOURCE_FAILURE.marker });

    assert.deepEqual(planIssueAction([existing], SOURCE_FAILURE), {
        action: 'comment',
        issueNumber: 42,
    });
    assert.deepEqual(planIssueAction([], SOURCE_FAILURE), { action: 'create' });
});

test('a closed finding stays closed instead of returning every month', () => {
    const closed = issue({ state: 'closed' });

    assert.deepEqual(planIssueAction([closed], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
        action: 'skip',
        issueNumber: 42,
    });
});

test('a finding that changed since it was closed opens a fresh issue', () => {
    // The closed issue records a judgement about the old finding, so it is left as it is.
    const closed = issue({ state: 'closed' });

    assert.deepEqual(planIssueAction([closed], { ...FINDING_REPORTS.standard, fingerprint: 'EE:20>24' }), {
        action: 'create',
    });
});

test('an open issue wins over a closed one carrying a different finding', () => {
    const closed = issue({ number: 7, state: 'closed', body: FINDING_REPORTS.standard.marker });
    const open = issue({ number: 42, state: 'open', body: FINDING_REPORTS.standard.marker });

    assert.deepEqual(planIssueAction([closed, open], { ...FINDING_REPORTS.standard, fingerprint: FINGERPRINT }), {
        action: 'update',
        issueNumber: 42,
    });
});

test('a closed source-failure issue is not commented on, since it carries no fingerprint', () => {
    const closed = issue({ title: SOURCE_FAILURE.title, state: 'closed', body: SOURCE_FAILURE.marker });

    assert.deepEqual(planIssueAction([closed], SOURCE_FAILURE), { action: 'create' });
});

test('findings render as tab-separated columns, and as nothing at all when there are none', () => {
    // A header-only block would read as a finding to buildReports, which reports on a truthy
    // output, so an empty finding set has to render as the empty string.
    for (const report of Object.values(FINDING_REPORTS)) {
        assert.equal(formatFindings(report, []), '');
    }

    assert.equal(DRIFT, 'Country\tCurrent\tPublished\nEE\t20\t24\nFI\t24\t25.5');
    assert.equal(REDUCED, 'Country\tCurrent\tTEDB reports\nRO\t5, 9\t11');

    assert.equal(
        formatFindings(FINDING_REPORTS.reduced, [{ iso: 'RO', current: [], published: ['11'] }]),
        'Country\tCurrent\tTEDB reports\nRO\t\u2014\t11',
    );
});

test('every body opens with its marker and carries its findings', () => {
    // planIssueAction matches on the marker, and skips on the fingerprint, so both have to be in
    // the body it reads back. The source-failure body must carry no fingerprint, or a repeat
    // failure would go unreported.
    const standard = buildFindingBody(FINDING_REPORTS.standard, DRIFT, FINGERPRINT);
    assert.ok(standard.startsWith(FINDING_REPORTS.standard.marker));
    assert.ok(standard.includes(fingerprintComment(FINGERPRINT)));
    assert.ok(standard.includes('EE\t20\t24'));

    const reduced = buildFindingBody(FINDING_REPORTS.reduced, REDUCED, 'RO:5/9>11');
    assert.ok(reduced.startsWith(FINDING_REPORTS.reduced.marker));
    assert.ok(reduced.includes(fingerprintComment('RO:5/9>11')));
    assert.ok(reduced.includes('RO\t5, 9\t11'));

    const failure = buildSourceFailureBody(RUN_URL);
    assert.ok(failure.startsWith(SOURCE_FAILURE.marker));
    assert.ok(rendersRunUrl(failure, RUN_URL));
    assert.ok(!failure.includes('<!-- fingerprint:'));
});

test('a verdict reports only the findings it has, as one issue each', () => {
    const standard = { STANDARD_DRIFT: DRIFT, STANDARD_FINGERPRINT: FINGERPRINT };
    const reduced = { REDUCED_CANDIDATES: REDUCED, REDUCED_FINGERPRINT: 'RO:5/9>11' };

    // Separate markers, because the two findings carry different trust levels and lifetimes.
    for (const [outputs, expected] of [
        [standard, [FINDING_REPORTS.standard.marker]],
        [reduced, [FINDING_REPORTS.reduced.marker]],
        [{ ...standard, ...reduced }, [FINDING_REPORTS.standard.marker, FINDING_REPORTS.reduced.marker]],
    ] as const) {
        const reports = buildReports({ CHECK_STATUS: '1', ...outputs }, RUN_URL);

        assert.deepEqual(
            reports.map((report) => report.marker),
            expected,
        );
    }

    const [only] = buildReports({ CHECK_STATUS: '1', ...standard }, RUN_URL);
    assert.equal(only.fingerprint, FINGERPRINT);
    assert.ok(only.body.includes('EE\t20\t24'));
    assert.ok(only.changed.includes('EE\t20\t24'));
});

test('any status other than 1 reports a source failure instead of a rate change', () => {
    for (const status of ['2', '3', '']) {
        const reports = buildReports({ CHECK_STATUS: status, STANDARD_DRIFT: DRIFT }, RUN_URL);

        // The drift output must be ignored: without a verdict it cannot be trusted.
        assert.deepEqual(
            reports.map((report) => report.marker),
            [SOURCE_FAILURE.marker],
            `status "${status}" must report a source failure`,
        );
        assert.equal(reports[0].body, buildSourceFailureBody(RUN_URL));
        assert.equal(reports[0].fingerprint, undefined);
    }
});
