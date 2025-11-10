#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PAYLOADS_DIR="${SCRIPT_DIR}/payloads"
OUTPUT_FILE="${SCRIPT_DIR}/sync-api-demo-payload.json"

# Parse command line arguments
SKIP_PATTERNS=()
while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip)
            if [[ -z "${2:-}" ]]; then
                echo "Error: --skip requires a file pattern" >&2
                exit 1
            fi
            SKIP_PATTERNS+=("$2")
            shift 2
            ;;
        *)
            echo "Error: Unknown option: $1" >&2
            echo "Usage: $0 [--skip FILE]..." >&2
            exit 1
            ;;
    esac
done

if [[ ! -d "${PAYLOADS_DIR}" ]]; then
    echo "Error: payloads directory not found at ${PAYLOADS_DIR}" >&2
    exit 1
fi

ALL_FILES=("${PAYLOADS_DIR}"/*.json)

if [[ ${#ALL_FILES[@]} -eq 0 ]]; then
    echo "Error: No JSON files found in ${PAYLOADS_DIR}" >&2
    exit 1
fi

# Filter files based on skip patterns
PAYLOAD_FILES=()
SKIPPED_FILES=()

for file in "${ALL_FILES[@]}"; do
    filename=$(basename "$file")
    skip=false

    for pattern in "${SKIP_PATTERNS[@]}"; do
        if [[ "$filename" == $pattern ]]; then
            SKIPPED_FILES+=("$filename")
            skip=true
            break
        fi
    done

    if [[ "$skip" == false ]]; then
        PAYLOAD_FILES+=("$file")
    fi
done

# Show what we found
echo "Found ${#ALL_FILES[@]} payload files"

if [[ ${#SKIPPED_FILES[@]} -gt 0 ]]; then
    for skipped in "${SKIPPED_FILES[@]}"; do
        echo "Skipping: $skipped"
    done
fi

if [[ ${#PAYLOAD_FILES[@]} -eq 0 ]]; then
    echo "Error: No files to process after applying skip patterns" >&2
    exit 1
fi

echo "Building sync API payload from ${#PAYLOAD_FILES[@]} files..."

jq -s 'add' "${PAYLOAD_FILES[@]}" > "${OUTPUT_FILE}"

if [[ $? -eq 0 ]]; then
    echo "✓ Successfully built ${OUTPUT_FILE}"
    echo "  Files included: ${#PAYLOAD_FILES[@]}/${#ALL_FILES[@]}"
    echo "  File size: $(wc -c < "${OUTPUT_FILE}" | awk '{print $1}') bytes"
    echo "  Lines: $(wc -l < "${OUTPUT_FILE}" | awk '{print $1}')"
else
    echo "✗ Failed to build payload" >&2
    exit 1
fi
