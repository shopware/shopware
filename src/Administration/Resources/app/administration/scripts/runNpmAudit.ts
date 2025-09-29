#!/usr/bin/env node
import { execSync } from "child_process";

// IDs of advisories to ignore
const ignored = [
  1103617, // axios - we cannot upgrade to 1.x due to breaking changes
  1107599, // axios - we cannot upgrade to 1.x due to breaking changes
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

  for (const pkgName in audit.vulnerabilities) {
    const pkg = audit.vulnerabilities[pkgName];
    if (pkg.via && Array.isArray(pkg.via)) {
      pkg.via = pkg.via.filter((v: any) => !ignored.includes(v.source));
    }
  }

  const remaining = Object.values(audit.vulnerabilities).reduce(
    (sum: number, pkg: any) => sum + (pkg.via.length > 0 ? 1 : 0),
    0
  );

  if (remaining > 0) {
    console.error(`❌ Remaining vulnerabilities detected: ${remaining}`);
    Object.values(audit.vulnerabilities)
      .filter((pkg: any) => pkg.via.length > 0)
      .forEach((pkg: any) => {
        pkg.via.forEach((v: any) => {
          console.error(`- ${v.title} (${v.source})`);
        });
      });
    process.exit(1);
  } else {
    console.log("✅ No vulnerabilities (ignored IDs excluded).");
  }
} catch (err: any) {
  console.error("Failed to parse npm audit JSON:", err.message);
  process.exit(1);
}