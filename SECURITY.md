# Security Policy — InstaRequest Community Edition (CE)

The security of **InstaRequest Community Edition (CE)** and its users is our highest priority. As an API client that handles request builders, headers, API keys, and authentication tokens, we take every potential vulnerability seriously.

This document outlines our supported versions, our private vulnerability disclosure process, response timelines, and baseline security practices.

---

## 📌 Supported Versions

We actively maintain and provide security patches for the latest major release branch of InstaRequest CE (`janakkapadia/insta-request-ce`).

| Version | Supported Status | Notes |
| :--- | :---: | :--- |
| **`1.x`** | :white_check_mark: **Supported** | Active development, critical bug fixes, and security patches. |
| **`< 1.0`** | :x: **Unsupported** | Early alpha/beta releases. End of Life (EOL). Please upgrade to `1.x`. |

If you are running an unsupported version, we strongly recommend upgrading to the latest `1.x` release immediately to ensure you receive the latest security enhancements and patches.

---

## 🚨 Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues, pull requests, discussions, or social media.** Publicly disclosing a security flaw before a patch is available puts all self-hosted and team deployments at risk.

We request that you report all potential security issues privately to the maintainers:

### 1. GitHub Private Vulnerability Reporting (Recommended)
You can report vulnerabilities securely and privately directly via GitHub:
1. Navigate to the [InstaRequest CE Repository Security Tab](https://github.com/janakkapadia/insta-request-ce/security).
2. Click on **Advisories** in the left sidebar.
3. Click **Report a vulnerability** to open a private, encrypted communication channel directly with the core maintainers.

### 2. Direct Email Disclosure
Alternatively, you may report vulnerabilities via email directly to the core maintainer leadership:
* **Email**: **`security@instawp.com`** (or directly to `janak@instawp.com` / `conduct@instawp.com`)
* **Subject**: `[SECURITY] InstaRequest CE - Vulnerability Report: <Brief Title>`

---

## 📋 What to Include in Your Report

To help our engineering team assess and resolve the issue quickly, please provide as much detail as possible:

* **Vulnerability Description**: A clear explanation of the flaw and the potential security impact (e.g., authentication bypass, IDOR, SSRF, data leakage, credential decryption).
* **Affected Components**: The specific files, endpoints, or modules involved (e.g., `ImportController.php`, `TokenController.php`, Environment Encryption service, `PublicViewer.vue`).
* **Steps to Reproduce**: Exact, step-by-step instructions, cURL requests, Postman payloads, or Proof-of-Concept (PoC) scripts to trigger the vulnerability.
* **Environment**: The PHP version, Laravel framework version, or InstaRequest release version where the issue was discovered.
* **Suggested Remediation (Optional)**: Any insights or proposed code changes to fix the issue safely.

---

## ⏱️ Response Timeline & Disclosure Policy

When you submit a private vulnerability report, we commit to the following response timeline and workflow:

1. **Acknowledgment (within 48 hours)**: We will acknowledge receipt of your report, verify that our team is investigating, and establish communication.
2. **Triage & Assessment (within 5 business days)**: We will reproduce the issue, determine its severity using the Common Vulnerability Scoring System (CVSS), and identify all affected versions.
3. **Patch Development & Verification**: Maintainers will develop, review, and rigorously test a security patch inside a private, restricted security branch.
4. **Release & Public Disclosure**:
   * We will publish a patched release (e.g., `v1.x.x`) to GitHub and package repositories.
   * A formal **GitHub Security Advisory** and **CVE** assignment (when applicable) will be published.
   * **Researcher Credit**: We will proudly credit you for discovering and responsibly disclosing the vulnerability in our advisory and release notes (unless you request to remain anonymous).

---

## 🔒 Baseline Security Architecture & Protections

InstaRequest CE is built from the ground up with defensive security best practices to protect developer data, API credentials, and internal networks:

* **Encrypted Environments (`AES-256-CBC` / `AES-256-GCM`)**: Sensitive environment variables and API keys stored inside InstaRequest collections are encrypted at rest in the database using Laravel's native encrypter (`app/Domains/Environments/`). They are decrypted only on the fly when executing a request or exporting by an authorized user.
* **Sanctum API Token Authentication**: All programmatic CI/CD integration endpoints under `/api/v1/*` (`/imports`, `/tokens`, `/collections`) are strictly authenticated via SHA-256 hashed **Laravel Sanctum** personal access tokens. Plaintext tokens are only shown once upon creation and never stored in the database.
* **CSRF & Session Security**: Browser-based dashboard and request editor routes (`Inertia.js` + `Vue 3`) are safeguarded against Cross-Site Request Forgery (CSRF) via strict cookie policies, session expiration, and XSRF headers.
* **SQL Injection & XSS Prevention**: All database queries strictly utilize Eloquent ORM parameter binding and prepared statements (`Pest` verified). Vue 3 Single File Components automatically escape user-provided strings and API response payloads to prevent Cross-Site Scripting (XSS).
* **No Mandatory Cloud Telemetry**: As an open-source Community Edition, InstaRequest CE keeps your API requests, headers, and environment credentials 100% locally within your infrastructure.

Thank you for helping keep InstaRequest Community Edition secure for everyone! 🛡️
