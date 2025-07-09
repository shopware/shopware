---
title: Fix YouTube video URL validation in CMS element
date: 2025-07-09
area: administration
tags: [cms, youtube, bugfix, validation]
---

## Context

The YouTube video CMS element configuration crashed when users entered video IDs directly (e.g., `bG57TZPYsyw`) instead of full URLs. The `shortenLink` method assumed all input would be valid URLs and attempted to construct a `new URL()` object, which threw a TypeError when passed a video ID string without the required URL protocol.

Additionally, the video link field displays only the video ID after saving, which can mislead users into believing that only IDs (not full URLs) are valid input, creating confusion about the expected input format.

## Decision

Added input validation to the `shortenLink` method in the YouTube video CMS element configuration:
- Check if input contains `://` to differentiate between URLs and video IDs
- Return video IDs immediately without URL processing
- Wrap URL construction in try-catch block for error handling
- Maintain backward compatibility with existing full URL inputs

## Consequences

- Users can now input both full YouTube URLs and video IDs without errors
- Improved user experience and reduced confusion about input format
- Better error handling prevents JavaScript crashes in the administration panel
- No breaking changes to existing functionality
