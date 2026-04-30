/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { DEFAULTS } from './constants';
import type { CliOptions, ParsedCliArguments } from './types';

const VALUE_OPTIONS = new Set([
    'profile',
    'commercial-path',
    'commercial-license-generator',
    'commercial-license-key-file',
    'commercial-license-host',
    'commercial-license-plan',
    'commercial-console-command',
    'components',
    'report-dir',
    'baseline-file',
]);

const BOOLEAN_OPTIONS = new Set([
    'force-license',
    'skip-build',
    'write-baseline',
]);

const OPTION_MAP: Record<string, keyof CliOptions> = {
    profile: 'profile',
    'commercial-path': 'commercialPath',
    'commercial-license-generator': 'commercialLicenseGenerator',
    'commercial-license-key-file': 'commercialLicenseKeyFile',
    'commercial-license-host': 'commercialLicenseHost',
    'commercial-license-plan': 'commercialLicensePlan',
    'commercial-console-command': 'commercialConsoleCommand',
    components: 'components',
    'report-dir': 'reportDir',
    'baseline-file': 'baselineFile',
    'force-license': 'forceLicense',
    'skip-build': 'skipBuild',
    'write-baseline': 'writeBaseline',
};

export function parseCliArguments(argv: string[]): ParsedCliArguments {
    const options: CliOptions = {
        ...DEFAULTS,
        components: [...DEFAULTS.components],
    };

    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];

        if (argument === '--help' || argument === '-h') {
            return { type: 'help', help: getHelpText() };
        }

        if (!argument.startsWith('--')) {
            return createError(`Unexpected argument "${argument}".`);
        }

        const [rawName, inlineValue] = argument.slice(2).split(/=(.*)/s, 2);

        if (BOOLEAN_OPTIONS.has(rawName)) {
            if (inlineValue !== undefined) {
                return createError(`Option "--${rawName}" does not accept a value.`);
            }

            setOptionValue(options, rawName, true);
            continue;
        }

        if (!VALUE_OPTIONS.has(rawName)) {
            return createError(`Unknown option "--${rawName}".`);
        }

        const value = inlineValue ?? argv[index + 1];

        if (value === undefined || value.startsWith('--')) {
            return createError(`Option "--${rawName}" requires a value.`);
        }

        if (inlineValue === undefined) {
            index += 1;
        }

        setOptionValue(options, rawName, value);
    }

    if (options.profile !== 'commercial') {
        return createError('Only "--profile commercial" is supported in the first implementation phase.');
    }

    return { type: 'options', options };
}

export function getHelpText(): string {
    return [
        'Usage: composer admin:plugin-compatibility -- [options]',
        '',
        'Local-only Administration plugin compatibility validation.',
        '',
        'Options:',
        `  --profile <name>                         Profile to run. Default: ${DEFAULTS.profile}`,
        `  --commercial-path <path>                 Commercial checkout. Default: ${DEFAULTS.commercialPath}`,
        `  --commercial-license-generator <command> License generator command. Default: ${DEFAULTS.commercialLicenseGenerator}`,
        '  --commercial-license-key-file <path>     Downloaded dev license JSON/plain key file for the bundled fallback.',
        `  --commercial-license-host <host>         License host. Default: ${DEFAULTS.commercialLicenseHost}`,
        `  --commercial-license-plan <plan>         License plan. Default: ${DEFAULTS.commercialLicensePlan}`,
        `  --commercial-console-command <command>   Console command. Default: ${DEFAULTS.commercialConsoleCommand}`,
        '  --force-license                         Regenerate the local Commercial license.',
        '  --components <list>                     Comma-separated component areas to smoke-test.',
        `  --report-dir <path>                      Report output directory. Default: ${DEFAULTS.reportDir}`,
        `  --baseline-file <path>                   Baseline JSON file. Default: ${DEFAULTS.baselineFile}`,
        '  --skip-build                            Skip the Administration build phase.',
        '  --write-baseline                        Write the current result as the local baseline.',
        '  --help                                  Show this help.',
    ].join('\n');
}

function createError(message: string): ParsedCliArguments {
    return { type: 'error', message, help: getHelpText() };
}

function setOptionValue(options: CliOptions, rawName: string, value: string | boolean): void {
    const optionName = OPTION_MAP[rawName];

    if (optionName === 'components') {
        options.components.push(...String(value).split(',').map((component) => component.trim()).filter(Boolean));

        return;
    }

    if (optionName === 'forceLicense' || optionName === 'skipBuild' || optionName === 'writeBaseline') {
        options[optionName] = Boolean(value);

        return;
    }

    options[optionName] = String(value);
}
