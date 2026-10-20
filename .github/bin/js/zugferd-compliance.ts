import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
    appendFileSync,
    existsSync,
    mkdirSync,
    mkdtempSync,
    readdirSync,
    readFileSync,
    writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { basename, dirname, join } from "node:path";

type Finding = { code: string; level: string; text: string };

type ReportAssessment = { rejected: boolean; findings: Finding[] };

type DocumentResult = { label: string; compliant: boolean; findings: Finding[] };

const KOSIT_VALIDATOR_VERSION = "1.6.2";
const KOSIT_VALIDATOR_JAR_SHA256 = "244978514ad48f67c7573acfffc8f4fd73d81feda6f276710033f9913579857e";
const KOSIT_VALIDATOR_JAR_URL = `https://github.com/itplr-kosit/validator/releases/download/v${KOSIT_VALIDATOR_VERSION}/validator-${KOSIT_VALIDATOR_VERSION}-standalone.jar`;
const KOSIT_CONFIG_RELEASES_API = "https://api.github.com/repos/itplr-kosit/validator-configuration-xrechnung/releases/latest";
const KOSIT_USAGE_ERROR_EXIT = 254;

const DATE_TOKEN = "[date]";
const DATE_REPLACEMENT = "20240101";

const ADVISORY_LEVELS = ["information"];

// Findings appear as <rep:message id="…" level="warning" code="PEPPOL-EN16931-R120" …>text</rep:message>.
const MESSAGE_PATTERN = /<(?:[\w.-]+:)?message\b([^>]*)>([\s\S]*?)<\/(?:[\w.-]+:)?message>/gi;

const SNAPSHOT_DIRS = [
    {
        domain: "v1",
        dir: "tests/integration/Core/Checkout/Document/Renderer/_snapshots",
    },
    {
        domain: "v2",
        dir: "tests/integration/Core/Checkout/DocumentV2/Renderer/_snapshots",
    },
];

export function isViolation(finding: Finding): boolean {
    return !ADVISORY_LEVELS.includes(finding.level);
}

function attribute(attributes: string, name: string): string {
    return new RegExp(`\\b${name}="([^"]*)"`).exec(attributes)?.[1] ?? "";
}

function log(message: string): void {
    process.stdout.write(`${message}\n`);
}

function sha256(file: string): string {
    return createHash("sha256").update(readFileSync(file)).digest("hex");
}

export function assessReport(reportXml: string): ReportAssessment {
    const findings = [...reportXml.matchAll(MESSAGE_PATTERN)].map(
        ([, attributes, text]) => ({
            code: attribute(attributes, "code"),
            level: attribute(attributes, "level"),
            text: text.trim(),
        }),
    );

    return {
        rejected: /<(?:[\w.-]+:)?reject[\s/>]/i.test(reportXml),
        findings,
    };
}

async function downloadToFile(
    url: string,
    dest: string,
    headers: Record<string, string> = {},
): Promise<void> {
    const response = await fetch(url, { headers, redirect: "follow" });

    if (!response.ok) {
        throw new Error(`download failed (${response.status}) for ${url}`);
    }

    const body = Buffer.from(await response.arrayBuffer());
    writeFileSync(dest, body);
}

function findFiles(dir: string, matches: (name: string) => boolean): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const fullPath = join(dir, entry.name);

        if (entry.isDirectory()) {
            return findFiles(fullPath, matches);
        }

        return matches(entry.name) ? [fullPath] : [];
    });
}

async function downloadValidator(workDir: string): Promise<string> {
    const jar = join(workDir, "validator.jar");

    log(`Downloading KoSIT validator ${KOSIT_VALIDATOR_VERSION} ...`);
    await downloadToFile(KOSIT_VALIDATOR_JAR_URL, jar);

    if (sha256(jar) !== KOSIT_VALIDATOR_JAR_SHA256) {
        throw new Error("validator jar checksum mismatch");
    }

    return jar;
}

async function resolveLatestConfigUrl(): Promise<string> {
    const token = process.env.GITHUB_TOKEN;

    const headers: Record<string, string> = {
        "User-Agent": "shopware-zugferd-compliance",
        Accept: "application/vnd.github+json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
    };

    const response = await fetch(KOSIT_CONFIG_RELEASES_API, { headers });

    if (!response.ok) {
        throw new Error(`could not query configuration release (${response.status})`);
    }

    const release = (await response.json()) as {
        assets?: Array<{ name: string; browser_download_url: string }>;
    };

    const assets = (release.assets ?? []).filter((candidate) =>
        /validator-configuration.*\.zip$/i.test(candidate.name),
    );

    if (assets.length === 0) {
        throw new Error("no configuration zip asset found in the latest release");
    }

    if (assets.length > 1) {
        throw new Error(
            `expected exactly one configuration zip asset, found ${assets.length}`,
        );
    }

    return assets[0].browser_download_url;
}

function unzip(zip: string, dest: string): void {
    try {
        execFileSync("unzip", ["-q", "-o", zip, "-d", dest], {
            stdio: "inherit",
        });
    } catch {
        throw new Error("could not unzip the configuration archive");
    }
}

async function downloadConfiguration(
    workDir: string,
): Promise<{ name: string; sha256: string; scenarios: string; repository: string }> {
    log("Resolving latest validator configuration ...");
    const configUrl = await resolveLatestConfigUrl();
    log(`Using configuration: ${configUrl}`);

    const configZip = join(workDir, "config.zip");
    await downloadToFile(configUrl, configZip);

    const configSha256 = sha256(configZip);
    log(`Configuration sha256: ${configSha256}`);

    const configDir = join(workDir, "config");
    mkdirSync(configDir, { recursive: true });
    unzip(configZip, configDir);

    const scenarios = findFiles(
        configDir,
        (name) => name === "scenarios.xml",
    )[0];

    if (!scenarios) {
        throw new Error("scenarios.xml not found in the configuration");
    }

    return {
        name: basename(configUrl),
        sha256: configSha256,
        scenarios,
        repository: dirname(scenarios),
    };
}

function stageSnapshots(stagingDir: string): string[] {
    return SNAPSHOT_DIRS.filter(({ dir }) => existsSync(dir)).flatMap(
        ({ domain, dir }) =>
            findFiles(dir, (name) => name.endsWith(".xml")).map((xml) => {
                const caseName = basename(dirname(xml));
                const content = readFileSync(xml, "utf8")
                    .split(DATE_TOKEN)
                    .join(DATE_REPLACEMENT);

                const stagedFile = join(
                    stagingDir,
                    `${domain}__${caseName}.xml`,
                );

                writeFileSync(stagedFile, content);

                return stagedFile;
            }),
    );
}

function runValidator(
    jar: string,
    scenarios: string,
    repository: string,
    reportDir: string,
    files: string[],
): number {
    const args = [
        "-jar",
        jar,
        "-s",
        scenarios,
        "-r",
        repository,
        "-h",
        "-o",
        reportDir,
        ...files,
    ];

    try {
        execFileSync("java", args, { stdio: ["ignore", "ignore", "inherit"] });

        return 0;
    } catch (error) {
        const status = (error as { status?: number }).status;

        if (typeof status !== "number") {
            throw new Error("could not run the validator (is Java installed?)");
        }

        return status;
    }
}

function collectResults(reportDir: string): DocumentResult[] {
    return findFiles(reportDir, (name) => name.endsWith("-report.xml"))
        .map((report) => {
            const { rejected, findings } = assessReport(
                readFileSync(report, "utf8"),
            );

            return {
                label: basename(report, "-report.xml").replace("__", " / "),
                compliant: !rejected && !findings.some(isViolation),
                findings,
            };
        })
        .sort((first, second) => first.label.localeCompare(second.label));
}

function buildSummary(
    configName: string,
    configSha256: string,
    results: DocumentResult[],
): string {
    const failedCount = results.filter((result) => !result.compliant).length;
    const heading =
        failedCount === 0
            ? `**All ${results.length} document(s) compliant**`
            : `**${failedCount} of ${results.length} document(s) failed**`;

    const rows = results
        .map(
            (result) =>
                `| ${result.label} | ${result.compliant ? "PASS" : "FAIL"} |`,
        )
        .join("\n");

    const findingLines = results
        .filter((result) => !result.compliant)
        .flatMap((result) =>
            result.findings.map(
                (finding) =>
                    `- ${result.label}: [${finding.level}] ${finding.code} — ${finding.text}`,
            ),
        );

    return [
        "## ZUGFeRD compliance",
        "",
        `${heading} — KoSIT ${KOSIT_VALIDATOR_VERSION}, config \`${configName}\`.`,
        "",
        `Config sha256: \`${configSha256}\``,
        "",
        "| Document | Result |",
        "| --- | --- |",
        rows,
        ...(findingLines.length > 0 ? ["", "### Findings", "", ...findingLines] : []),
    ].join("\n");
}

function writeStepSummary(markdown: string): void {
    if (!process.env.GITHUB_STEP_SUMMARY) {
        return;
    }

    appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${markdown}\n`);
}

function setStepOutput(name: string, value: string): void {
    if (!process.env.GITHUB_OUTPUT) {
        return;
    }

    appendFileSync(
        process.env.GITHUB_OUTPUT,
        `${name}<<ZUGFERD_COMPLIANCE\n${value}\nZUGFERD_COMPLIANCE\n`,
    );
}

async function main(): Promise<void> {
    const workDir = process.env.WORK_DIR ?? mkdtempSync(join(tmpdir(), "zugferd-"));

    const stagingDir = join(workDir, "staging");
    const reportDir = join(workDir, "reports");

    mkdirSync(stagingDir, { recursive: true });
    mkdirSync(reportDir, { recursive: true });

    const [jar, config] = await Promise.all([
        downloadValidator(workDir),
        downloadConfiguration(workDir),
    ]);

    const staged = stageSnapshots(stagingDir);

    if (staged.length === 0) {
        throw new Error("no snapshot XML files found to validate");
    }

    log(`Checking ${staged.length} ZUGFeRD document(s) against KoSIT ${KOSIT_VALIDATOR_VERSION} (${config.name}).`);
    log("");

    const validatorExit = runValidator(
        jar,
        config.scenarios,
        config.repository,
        reportDir,
        staged,
    );

    if (validatorExit >= KOSIT_USAGE_ERROR_EXIT) {
        throw new Error(`validator reported a configuration/argument error (exit ${validatorExit})`);
    }

    const results = collectResults(reportDir);

    if (results.length !== staged.length) {
        throw new Error(
            `expected one report per document, staged ${staged.length} but found ${results.length}`,
        );
    }

    for (const result of results) {
        log(`  ${result.compliant ? "PASS" : "FAIL"}  ${result.label}`);

        for (const finding of result.findings) {
            log(`        [${finding.level}] ${finding.code}: ${finding.text}`);
        }
    }

    log("");

    const failed = results.filter((result) => !result.compliant);

    writeStepSummary(buildSummary(config.name, config.sha256, results));
    setStepOutput("config_name", config.name);
    setStepOutput("config_sha256", config.sha256);
    setStepOutput("document_count", String(staged.length));
    setStepOutput("failed_count", String(failed.length));
    setStepOutput(
        "failed_documents",
        failed
            .map((result) => {
                const codes = [
                    ...new Set(result.findings.map((finding) => finding.code)),
                ];

                return `- ${result.label}${codes.length > 0 ? ` (${codes.join(", ")})` : ""}`;
            })
            .join("\n"),
    );

    if (failed.length === 0) {
        log(`All ${staged.length} document(s) are ZUGFeRD compliant.`);

        return;
    }

    log(`${failed.length} of ${staged.length} document(s) failed compliance. See the 'zugferd-compliance-reports' artifact.`);
    process.exit(1);
}

main().catch((error: unknown) => {
    const message = error instanceof Error ? error.message : String(error);

    process.stderr.write(`::error::ZUGFeRD compliance check infrastructure error: ${message}\n`);
    process.exit(2);
});
