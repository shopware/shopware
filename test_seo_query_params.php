<?php

/**
 * Simple test to reproduce the SEO URL query parameter issue
 */

// Mock the issue scenario
$seoPathInfo = "Main-product/SWDEMO10001?test=123";
echo "Original seo path info: " . $seoPathInfo . "\n";

// This is what happens in SeoResolver::resolve line 34
$trimmedPath = trim($seoPathInfo, '/');
echo "Trimmed path: " . $trimmedPath . "\n";

// The issue: query parameters are included in the path lookup
echo "Query parameters are still included in the path for database lookup!\n";

// Show how the URL would be processed
$parsedUrl = parse_url($seoPathInfo);
echo "Parsed URL components:\n";
print_r($parsedUrl);

// The correct approach would be to separate the path from query
if (isset($parsedUrl['query'])) {
    $pathWithoutQuery = $parsedUrl['path'] ?? '';
    $queryString = $parsedUrl['query'];
    echo "Path without query: " . $pathWithoutQuery . "\n";
    echo "Query string: " . $queryString . "\n";
}

echo "\nThis shows the issue: the query parameters are being included in the SEO path lookup!\n";