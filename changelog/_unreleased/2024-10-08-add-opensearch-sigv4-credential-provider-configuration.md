---
title: Add opensearch sigv4 credential provider configuration
issue: NEXT-38534
---
# Core
* Changed `createClient` method in `src/Elasticsearch/Framework/ClientFactory.php` to set opensearch sigv4 credential provider configuration
* Added `src/Elasticsearch/Framework/AsyncAwsSigner.php` to handle sigv4 credential provider configuration
