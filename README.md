<div align="center">
    <h1>🚀 InstaRequest Community Edition (CE)</h1>
    <p><b>The lightweight, modern Postman alternative built for developer teams.</b></p>
</div>

---

## 💡 Why InstaRequest?

Postman has become bloated, slow, and increasingly forces mandatory cloud syncing. We built **InstaRequest** because we wanted a clean, native-feeling API tool that puts you in control of your data.

InstaRequest Community Edition offers everything you need to build, test, and document your APIs, wrapped in a beautiful UI powered by Vue 3 and Tailwind CSS, backed by a robust Laravel 13 engine.

## 📸 Preview

### Request Builder & Collections
![Request Builder](./public/screenshots/request-builder.png?v=3)

### Dashboard Overview
![Dashboard](./public/screenshots/dashboard.png?v=3)

### Command Palette & Quick Navigation
![Command Palette](./public/screenshots/command-palette.png?v=3)

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

## ⚙️ Installation

Follow these steps to set up InstaRequest Community Edition locally:

### 1. Prerequisites

Ensure you have the following installed on your system:
- **PHP** >= 8.3 (with extensions such as `sqlite3`, `mbstring`, `xml`, `curl`)
- **Composer** (PHP package manager)
- **Node.js** >= 20.x & **npm**

### 2. Clone the Repository

```bash
git clone https://github.com/janakkapadia/insta-request-ce.git
cd insta-request-ce
```

### 3. Quick Setup

InstaRequest includes a built-in setup script that automates package installation, environment setup, application key generation, database migrations (using a local SQLite database by default), and asset compilation:

```bash
composer run setup
```

### 4. Run the Development Server

Start both the Laravel backend server and the Vite frontend compiler concurrently using:

```bash
npm run dev
```

Once started, open `http://127.0.0.1:8000` in your browser.

## ⌨️ Keyboard Shortcuts

InstaRequest comes with built-in shortcuts to speed up your workflow:

| Action | macOS | Windows / Linux |
| :--- | :--- | :--- |
| **Command Palette** | `Cmd + K` | `Ctrl + K` |
| **New Request** | `Cmd + T` (Desktop) or `Option + T` (Web) | `Alt + T` |
| **Open Request in New Tab** | `Cmd + Click` (or Middle Click) | `Ctrl + Click` (or Middle Click) |
| **Close Current Tab** | `Cmd + W` or `Option + W` | `Ctrl + W` or `Alt + W` |
| **Save Request** | `Cmd + S` or `Option + S` | `Ctrl + S` or `Alt + S` |

## 🤝 Contributing
Contributions are welcome! Please submit a pull request or open an issue to discuss proposed changes.

## 📄 License
The InstaRequest Community Edition source code is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://buymeacoffee.com/janak.kapadia)
