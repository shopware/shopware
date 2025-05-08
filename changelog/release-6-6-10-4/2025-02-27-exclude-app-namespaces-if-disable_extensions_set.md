---
title: Exclude app namespaces in BundleHierarchyBuilder if DISABLE_EXTENSIONS is set
issue: 6842
---
# Core
* Changed the `\Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder` to exclude app namespaces if `DISABLE_EXTENSIONS` environment variable is set.
