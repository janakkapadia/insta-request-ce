<div align="center">
    <h1>🚀 InstaRequest Community Edition (CE)</h1>
    <p><b>The lightweight, modern Postman alternative built for developer teams.</b></p>
</div>

---

## 💡 Why InstaRequest?

Postman has become bloated, slow, and increasingly forces mandatory cloud syncing. We built **InstaRequest** because we wanted a clean, native-feeling API tool that puts you in control of your data.

InstaRequest Community Edition offers everything you need to build, test, and document your APIs, wrapped in a beautiful UI powered by Vue 3 and Tailwind CSS, backed by a robust Laravel 13 engine.

## ✨ Features

- **⚡ Fast Request Builder:** Intuitive interface for constructing complex API requests with custom headers, query parameters, path variables, and body payloads.
- **📁 Team Collections & Folders:** Organize your requests cleanly into nested folders and share collections across your workspace.
- **🔒 Encrypted Environments:** Securely store your environment variables and API keys. InstaRequest automatically encrypts and decrypts credentials on the fly using AES-256 encryption.
- **📜 Executable History:** Automatically track request execution history.
- **📖 Documentation Portals:** Generate and publish both internal team documentation and public-facing API portals.
- **📦 Import/Export:** Easily migrate your data in and out.

## 🛠️ Tech Stack

InstaRequest is built on modern, developer-friendly technologies:
- **Backend:** Laravel Framework 13.x
- **Frontend:** Vue.js 3 + Inertia.js
- **Styling:** Tailwind CSS v4 + Reka UI
- **Testing:** Pest

## ⌨️ Keyboard Shortcuts

InstaRequest comes with built-in shortcuts to speed up your workflow:

| Action | macOS | Windows / Linux |
| :--- | :--- | :--- |
| **Command Palette** | `Cmd + K` | `Ctrl + K` |
| **Open Request in New Tab** | `Cmd + Click` (or Middle Click) | `Ctrl + Click` (or Middle Click) |

## 🚀 Deployment

InstaRequest uses [Deployer](https://deployer.org/) for zero-downtime deployments. 

If you have deploy access, you can push the latest code to production with a single command:

```bash
./vendor/bin/dep deploy prod
```

This command automatically pulls the latest `main` branch, installs NPM dependencies, compiles assets, runs database migrations, and safely restarts the queue workers.

## 🤝 Contributing
Contributions are welcome! Please submit a pull request or open an issue to discuss proposed changes.

## 📄 License
The InstaRequest Community Edition source code is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
