# Contributing to InstaRequest Community Edition (CE)

First off, thank you for considering contributing to **InstaRequest Community Edition (CE)**! It's people like you that make open-source API tools powerful, fast, and accessible for developer teams everywhere.

Whether you're reporting a bug, proposing a new feature, improving documentation, or submitting code fixes and new capabilities, your contributions are sincerely appreciated.

---

## 📜 Code of Conduct

By participating in this project and community, you agree to abide by our [Code of Conduct](file:///Users/janakkapadia/code/insta-request-ce/CODE_OF_CONDUCT.md). Please read it before interacting or submitting contributions to ensure a welcoming, professional, and inclusive community environment.

---

## 🚀 Getting Started (Local Development Setup)

### 1. Prerequisites
Ensure your local environment meets the following requirements:
* **PHP**: `>= 8.3` (with `sqlite3`, `mbstring`, `xml`, `curl`, and `zip` extensions)
* **Composer**: Latest 2.x
* **Node.js**: `>= 20.x` & **npm** (`>= 10.x`)
* **Git**

### 2. Fork & Clone
1. Fork the repository on GitHub: [https://github.com/janakkapadia/insta-request-ce](https://github.com/janakkapadia/insta-request-ce)
2. Clone your fork locally:
   ```bash
   git clone https://github.com/<your-username>/insta-request-ce.git
   cd insta-request-ce
   ```
3. Add the upstream repository so you can easily sync future updates:
   ```bash
   git remote add upstream https://github.com/janakkapadia/insta-request-ce.git
   ```

### 3. Automated Quick Setup
We provide a unified Composer setup command that installs PHP dependencies, installs Node packages, copies `.env.example` to `.env`, generates the application encryption key, creates the local SQLite database (`database/database.sqlite`), and runs initial migrations:
```bash
composer run setup
```

*(If you prefer manual setup, you can run `composer install`, `npm install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, and `php artisan migrate`).*

### 4. Running the Development Server
To start the Laravel backend development server and the Vite frontend Hot Module Replacement (HMR) compiler concurrently, run:
```bash
npm run dev
```
Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your web browser.

---

## 🌿 Development Workflow & Branching

1. Always sync your `main` branch with upstream before starting new work:
   ```bash
   git checkout main
   git pull upstream main
   ```
2. Create a descriptive feature or bugfix branch:
   ```bash
   git checkout -b feat/add-graphql-import
   # or
   git checkout -b fix/header-validation-bug
   ```
3. Make your changes in small, logical commits.

### Commit Message Guidelines
We follow [Conventional Commits](https://www.conventionalcommits.org/). Please format your commit messages clearly:
* `feat: ...` — A new feature or capability
* `fix: ...` — A bug fix
* `docs: ...` — Documentation adjustments
* `refactor: ...` — Code restructuring without behavior changes
* `test: ...` — Adding or modifying automated tests
* `chore: ...` — Maintenance tasks, dependencies, or build scripts

Example: `feat(api): add one-shot import endpoint with mirror strategy (#5)`

---

## 📐 Coding Standards & Quality Checks

We enforce automated code formatting and static checks to maintain high code health across both PHP and TypeScript/Vue codebases.

### Backend (PHP 8.3 / Laravel 13.x)
* **Code Style**: We use **[Laravel Pint](https://laravel.com/docs/pint)**.
  * To check for formatting issues: `composer lint:check`
  * To automatically fix PHP formatting: `composer lint`
* **Architecture**: Follow existing domain structures under `app/Domains/` (`ImportExport`, `RequestExecution`, etc.) or `app/Http/Controllers/Api/V1/` for REST API controllers. Use strong typing (`declare(strict_types=1);` where applicable, return types, and typed properties).

### Frontend (Vue 3 / TypeScript / Tailwind CSS v4 / Inertia.js)
* **Code Style & Linting**: We use **Prettier** and **ESLint**.
  * Auto-format frontend code: `npm run format`
  * Auto-fix frontend lint errors: `npm run lint`
* **Type Checking**: Ensure all TypeScript and Vue Single File Components (SFCs) pass strict type checking without errors:
  ```bash
  npm run types:check
  ```
*(We use `vue-tsc --noEmit` to verify type safety across all Vue components).*

---

## 🧪 Testing Guidelines

We use **[Pest PHP](https://pestphp.com/)** for backend testing. **Every bug fix or new feature must include automated tests** to prevent future regressions.

### Running Tests
* Run all backend feature & unit tests directly:
  ```bash
  ./vendor/bin/pest
  ```
* Run a specific test file or filter by test name:
  ```bash
  ./vendor/bin/pest tests/Feature/ApiV1Test.php --filter="one-shot import"
  ```
* Run the full PHP test pipeline (clears config + runs Pint check + runs Pest):
  ```bash
  composer test
  ```

### The Ultimate Pre-Push CI Check
Before committing and submitting your pull request, run our comprehensive CI verification command to ensure your branch will pass all automated GitHub checks:
```bash
composer ci:check
```
This runs:
1. `npm run lint:check` (ESLint)
2. `npm run format:check` (Prettier)
3. `npm run types:check` (`vue-tsc`)
4. `composer test` (Pint + Pest PHP suite)

---

## 📥 Submitting a Pull Request (PR)

When your code is tested and ready:
1. Push your branch to your GitHub fork:
   ```bash
   git push -u origin feat/add-graphql-import
   ```
2. Open a Pull Request against the `main` branch of `janakkapadia/insta-request-ce`.
3. In your PR description:
   * Provide a clear summary of what changes were made and why.
   * Reference any related GitHub issue numbers (e.g., `Resolves #15` or `Fixes #23`).
   * Confirm that `composer ci:check` passed successfully locally.
   * If modifying UI components (`resources/js/`), attach before/after screenshots or screen recordings.

---

## 🐞 Reporting Bugs & Requesting Features

### Bug Reports
Before opening a new bug report, please check [existing issues](https://github.com/janakkapadia/insta-request-ce/issues) to avoid duplicates. When opening a bug report, include:
* Your operating system and browser version.
* PHP and Node.js versions (`php -v` and `node -v`).
* Exact, step-by-step instructions to reproduce the issue.
* Any relevant error logs, stack traces, or console messages.

### Feature Requests
Have an idea to make InstaRequest CE better? Open a feature request issue! Please describe:
* The problem you are trying to solve or workflow bottleneck.
* Your proposed solution or desired API/UI behavior.
* Any alternatives you considered.

---

## 💬 Questions & Community Support

If you need help getting your local environment set up or want to discuss an architectural idea before starting work, feel free to open a [GitHub Discussion](https://github.com/janakkapadia/insta-request-ce/discussions) or reach out to the core maintainers.

Thank you once again for building with and contributing to InstaRequest Community Edition! 🚀
