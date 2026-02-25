#!/usr/bin/env node
import { execSync } from "child_process";

// IDs of advisories to ignore
const ignored: number[] = [
  1112030, // elliptic, low severity, devDep only (vite-plugin-node-polyfills/crypto-browserify)
  1112455, // lodash prototype pollution, moderate severity, devDep only
  1112453, // lodash-es prototype pollution, moderate severity, devDep only
  1113371, // minimatch ReDoS, high severity, devDep only, needs ESLint 9 migration to drop eslint-plugin-import
  1113428, // ajv ReDoS, moderate severity, devDep only (webpack/schema-utils), no fix for ajv 6.x line
  1113429, // ajv ReDoS, moderate severity, devDep only (webpack/schema-utils), no fix for ajv 6.x line
  1113402, // bn.js infinite loop, moderate severity, devDep only (vite-plugin-node-polyfills/crypto-browserify)
  1113275, // axios DoS, high severity, false positive: installed 0.30.3 is not in affected range 1.0.0-1.13.4
];
let auditRaw = "";

try {
  // Capture stdout even if npm audit exits with code 1
  auditRaw = execSync("npm audit --json", {
    encoding: "utf8"
  });
} catch (err: any) {
  if (err.stdout) {
    auditRaw = err.stdout.toString();
  } else {
    console.error("Error running npm audit:", err.message);
    process.exit(1);
  }
}

try {
  const audit = JSON.parse(auditRaw);

  // First pass: filter out ignored advisories
  for (const pkgName in audit.vulnerabilities) {
    const pkg = audit.vulnerabilities[pkgName];
    if (pkg.via && Array.isArray(pkg.via)) {
      pkg.via = pkg.via.filter((v: any) => !ignored.includes(v.source));
    }
  }

  // Second pass: remove packages that only have transitive dependencies on filtered packages
  let changed = true;
  while (changed) {
    changed = false;
    for (const pkgName in audit.vulnerabilities) {
      const pkg = audit.vulnerabilities[pkgName];
      if (pkg.via && Array.isArray(pkg.via) && pkg.via.length > 0) {
        // Filter out string references to packages that have no vulnerabilities left
        pkg.via = pkg.via.filter((v: any) => {
          if (typeof v === 'string') {
            // Check if the referenced package has any vulnerabilities left
            const refPkg = audit.vulnerabilities[v];
            return refPkg && refPkg.via && refPkg.via.length > 0;
          }
          return true; // Keep object entries that weren't filtered in first pass
        });
        if (pkg.via.length === 0) {
          changed = true;
        }
      }
    }
  }

  const remaining = Object.values(audit.vulnerabilities).reduce(
    (sum: number, pkg: any) => sum + (pkg.via.length > 0 ? 1 : 0),
    0
  );

  if (remaining > 0) {
    console.error(`❌ Remaining vulnerabilities detected: ${remaining}\n`);
    Object.values(audit.vulnerabilities)
      .filter((pkg: any) => pkg.via.length > 0)
      .forEach((pkg: any) => {
        console.error(`Package: ${pkg.name || 'unknown'}`);
        console.error(`Severity: ${pkg.severity || 'unknown'}`);
        console.error(`Range: ${pkg.range || 'N/A'}`);
        pkg.via.forEach((v: any) => {
          // Handle both string and object types in via array
          if (typeof v === 'string') {
            console.error(`  - Dependency issue: ${v}`);
          } else if (v && typeof v === 'object') {
            const title = v.title || 'Unknown vulnerability';
            const source = v.source || 'N/A';
            const url = v.url || (v.source ? `https://github.com/advisories/GHSA-${v.source}` : null);

            console.error(`  - ${title}`);
            console.error(`    Advisory ID: ${source}`);
            if (url) {
              console.error(`    URL: ${url}`);
            }
          }
        });
        console.error('');
      });
    process.exit(1);
  } else {
    console.log("✅ No vulnerabilities (ignored IDs excluded).");
  }
} catch (err: any) {
  console.error("Failed to parse npm audit JSON:", err.message);
  process.exit(1);
}
