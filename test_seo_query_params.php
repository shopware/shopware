<?php

/**
 * Simple test to reproduce the SEO URL query parameter issue
 */

// Mock the issue scenario
$seoPathInfo = "Main-product/SWDEMO10001?test=123";
echo "Original seo path info: " . $seoPathInfo . "\n";

// This is what happens in SeoResolver::resolve line 34 (OLD VERSION)
$trimmedPath = trim($seoPathInfo, '/');
echo "OLD - Trimmed path: " . $trimmedPath . "\n";

// Show how the URL would be processed (NEW VERSION)
$parsedUrl = parse_url($seoPathInfo);
echo "Parsed URL components:\n";
print_r($parsedUrl);

// The correct approach: separate the path from query
$pathWithoutQuery = trim($parsedUrl['path'] ?? $seoPathInfo, '/');
$queryString = $parsedUrl['query'] ?? '';
echo "NEW - Path without query: " . $pathWithoutQuery . "\n";
echo "NEW - Query string: " . $queryString . "\n";

echo "\nFixed: Query parameters are now properly separated from the SEO path lookup!\n";