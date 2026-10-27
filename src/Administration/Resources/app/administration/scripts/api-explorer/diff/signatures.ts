/**
 * @sw-package framework
 */

import type { CallSignatureEntry, MethodEntry, ParameterEntry } from '../snapshot/types';
import { describeTextChange, normalizeText, textChanged, type Finding } from './changes';

function isRequired(parameter: ParameterEntry): boolean {
    return !parameter.optional && !parameter.rest;
}

export function renderParameters(parameters: ParameterEntry[]): string {
    return parameters
        .map(
            (parameter) =>
                `${parameter.rest ? '...' : ''}${parameter.name}${parameter.optional ? '?' : ''}: ${normalizeText(parameter.type)}`,
        )
        .join(', ');
}

export function renderMethod(method: MethodEntry): string {
    return `${method.async ? 'async ' : ''}${method.name}(${renderParameters(method.parameters)}): ${normalizeText(method.returnType)}`;
}

function compareParameter(base: ParameterEntry, head: ParameterEntry, prefix: string): Finding[] {
    const findings: Finding[] = [];
    const label = `${prefix}Parameter \`${head.name}\``;

    if (base.name !== head.name) {
        findings.push({
            severity: 'compatible',
            change: 'changed',
            reason: `${prefix}Parameter \`${base.name}\` renamed to \`${head.name}\`.`,
        });
    }

    if (!isRequired(base) && isRequired(head)) {
        findings.push({ severity: 'breaking', change: 'changed', reason: `${label} is now required.` });
    } else if (isRequired(base) && !isRequired(head)) {
        findings.push({ severity: 'compatible', change: 'changed', reason: `${label} is now optional.` });
    }

    if (base.rest !== head.rest) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: `${label} ${head.rest ? 'is now' : 'is no longer'} a rest parameter.`,
        });
    }

    if (textChanged(base.type, head.type)) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: describeTextChange(`${label} type`, base.type, head.type),
        });
    }

    return findings;
}

function compareParameters(base: ParameterEntry[], head: ParameterEntry[], prefix: string): Finding[] {
    const findings: Finding[] = [];

    for (let index = 0; index < Math.max(base.length, head.length); index += 1) {
        const baseParameter = base[index];
        const headParameter = head[index];

        const headIndex = baseParameter ? head.findIndex((candidate) => candidate.name === baseParameter.name) : -1;

        if (baseParameter && headIndex !== -1 && headIndex !== index) {
            findings.push({
                severity: 'potentiallyBreaking',
                change: 'changed',
                reason: `${prefix}Parameter \`${baseParameter.name}\` moved from position ${index + 1} to ${headIndex + 1}.`,
            });
        }

        if (baseParameter && headParameter) {
            findings.push(...compareParameter(baseParameter, headParameter, prefix));
        } else if (baseParameter) {
            findings.push(
                isRequired(baseParameter)
                    ? {
                          severity: 'breaking',
                          change: 'changed',
                          reason: `${prefix}Required parameter \`${baseParameter.name}\` removed.`,
                      }
                    : {
                          severity: 'potentiallyBreaking',
                          change: 'changed',
                          reason: `${prefix}Optional parameter \`${baseParameter.name}\` removed.`,
                      },
            );
        } else if (headParameter) {
            findings.push(
                isRequired(headParameter)
                    ? {
                          severity: 'breaking',
                          change: 'changed',
                          reason: `${prefix}New required parameter \`${headParameter.name}\`.`,
                      }
                    : {
                          severity: 'compatible',
                          change: 'changed',
                          reason: `${prefix}New optional parameter \`${headParameter.name}\`.`,
                      },
            );
        }
    }

    return findings;
}

function compareReturnType(base: string, head: string, prefix: string): Finding[] {
    if (!textChanged(base, head)) {
        return [];
    }

    return [
        {
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: describeTextChange(`${prefix}Return type`, base, head),
        },
    ];
}

export function compareMethods(base: MethodEntry, head: MethodEntry): Finding[] {
    const findings = [
        ...compareParameters(base.parameters, head.parameters, ''),
        ...compareReturnType(base.returnType, head.returnType, ''),
    ];

    if (base.async !== head.async) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: head.async ? 'Now async.' : 'No longer async.',
        });
    }

    return findings;
}

/** Compares overloads pairwise in declaration order. */
export function compareCallSignatures(base: CallSignatureEntry[], head: CallSignatureEntry[]): Finding[] {
    const findings: Finding[] = [];
    const overloaded = base.length > 1 || head.length > 1;

    if (base.length !== head.length) {
        findings.push({
            severity: 'potentiallyBreaking',
            change: 'changed',
            reason: `Number of call signatures changed from ${base.length} to ${head.length}.`,
        });
    }

    for (let index = 0; index < Math.min(base.length, head.length); index += 1) {
        const prefix = overloaded ? `Overload ${index + 1}: ` : '';
        findings.push(
            ...compareParameters(base[index].parameters, head[index].parameters, prefix),
            ...compareReturnType(base[index].returnType, head[index].returnType, prefix),
        );
    }

    return findings;
}
