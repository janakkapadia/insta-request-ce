<!--
Thank you for contributing to InstaRequest Community Edition (CE)! 🚀
Please fill out this template to help reviewers clearly understand your changes and verify them efficiently.
-->

### 📝 Summary of Changes
<!-- Provide a concise description of what this pull request changes or adds. Explain the motivation and context. -->


### 🔗 Related Issue(s)
<!-- Link to any relevant open issues this PR resolves or addresses (e.g., "Fixes #12", "Resolves #45", "Addresses #89"). -->
- Fixes #

### 🏷️ Type of Change
<!-- Check all options that apply by changing [ ] to [x] -->
- [ ] 🐞 **Bug fix** (non-breaking change which fixes an issue)
- [ ] ✨ **New feature** (non-breaking change which adds new functionality)
- [ ] 💥 **Breaking change** (fix or feature that would cause existing functionality or API contracts to change)
- [ ] 📚 **Documentation update** (README, CONTRIBUTING, API docs, or code comments)
- [ ] 🛠️ **Refactoring / Performance** (code improvements without functional behavior changes)
- [ ] ⚙️ **CI / Build / Chore** (tooling, dependencies, GitHub Actions workflows)

---

### ✅ Verification & Testing Checklist
<!-- Confirm that your changes have been thoroughly tested locally and check all boxes before submitting. -->
- [ ] I have run `composer ci:check` locally and confirmed that all static analysis, linting, type checks (`vue-tsc`), and backend tests (`pest`) pass without errors.
- [ ] I have verified that any new/modified API routes or UI workflows work correctly under local development (`composer run dev`).
- [ ] I have added or updated automated unit/feature tests (`tests/Feature/` or `tests/Unit/`) covering the new functionality or bug fix.

#### 🧪 Local Reproduction / Verification Steps
<!-- Describe how a reviewer can manually test and verify this pull request locally: -->
1. 
2. 
3. 

#### 📸 Screenshots / Screen Recordings (if applicable)
<!-- If this PR modifies or introduces frontend UI components (`resources/js/`), please attach before/after screenshots or a short Loom/GIF demonstrating the visual interaction: -->
| Before | After |
| :---: | :---: |
| <!-- attach before image --> | <!-- attach after image --> |

---

### 📋 Final Pre-Submission Checklist
- [ ] My code strictly follows the existing coding style and formatting conventions of the project (`pint` and `eslint`).
- [ ] I have self-reviewed my diff to ensure no scratch/temporary files, debug `console.log()` / `dd()`, or sensitive keys are committed.
- [ ] My changes do not generate any new TypeScript compilation warnings or Vue prop mutations.
- [ ] If introducing new environment variables (`.env`), I have documented them in `.env.example` and `CONTRIBUTING.md`.
