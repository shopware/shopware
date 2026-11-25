/**
 * @sw-package framework
 *
 * The lines and sections the outline of a report is made of, and the markers that follow a line.
 */
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import type { FlagContext, FlagStates } from '../model/flag-states';

/**
 * @private
 *
 * A line of the outline. Its markers follow the text in brackets, without the brackets here.
 */
export interface Line {
    text: string;
    markers: string[];
}

/** @private */
export interface Section {
    heading: string;
    lines: Line[];
}

/** @private */
export function line(text: string, ...markers: (string | null)[]): Line {
    return { text, markers: markers.filter((marker) => marker !== null) };
}

/**
 * @private
 *
 * How a line shows the `@deprecated` and `@bc-change` markers of its element. The message of a marker is left
 * out, and of its keys only the parameter it names is shown.
 */
export function markerLabels(markers: Marker[]): string[] {
    return markers.map((marker) =>
        marker.tag === 'deprecated'
            ? `deprecated tag:${marker.version}`
            : [
                  'bc-change',
                  marker.kind,
                  marker.version,
                  marker.parameters.parameter,
              ]
                  .filter(Boolean)
                  .join(' '),
    );
}

/**
 * @private
 *
 * The marker of an element that exists in one flag state only, without brackets.
 */
export function flagStateMarker(states: FlagStates, flags: FlagContext): string | null {
    if (states.current === states.major) {
        return null;
    }

    return `${states.current ? 'until' : 'from'} ${flags.majorVersion}`;
}
