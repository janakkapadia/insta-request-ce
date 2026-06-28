# Project Memory

## Overview
This is a web application built using the **Laravel** framework for the backend and **Vue.js** with **Inertia.js** for the frontend. The project appears to be based on or named `laravel/vue-starter-kit`.

## Tech Stack
### Backend
- **Framework**: Laravel 11.x (implied by `laravel/framework` ^13.7? Actually, `laravel/framework: ^13.7` doesn't exist yet, it's likely a custom or future version, but requires PHP `^8.3`)
- **Testing**: Pest (`pestphp/pest`)
- **WebSockets**: Laravel Reverb (`laravel/reverb`)
- **Authentication/API**: Laravel Sanctum (`laravel/sanctum`), Laravel Fortify (`laravel/fortify`)
- **Routing Extension**: `laravel/wayfinder`

### Frontend
- **Framework**: Vue 3 (`vue: ^3.5.13`)
- **Integration**: Inertia.js (`@inertiajs/vue3`, `@inertiajs/vite`)
- **Build Tool**: Vite
- **Styling**: Tailwind CSS v4 (`tailwindcss: ^4.1.1`, `@tailwindcss/vite`)
- **Component Library/Primitives**: Reka UI (`reka-ui`), Lucide Icons (`lucide-vue-next`), Tailwind Merge (`tailwind-merge`), Class Variance Authority (`class-variance-authority`), similar to a **shadcn/ui** setup.
- **State Management**: Pinia (`pinia: ^3.0.4`)
- **Code Editor**: Monaco Editor (`monaco-editor`, `@guolao/vue-monaco-editor`)
- **Utilities**: VueUse (`@vueuse/core`)
- **Language**: TypeScript (`typescript`, `vue-tsc`)

## Directory Structure
- `app/`: Laravel backend code (Controllers, Models, etc.)
- `resources/`: Frontend code (Vue components, Inertia pages, CSS)
- `routes/`: Laravel routing files
- `tests/`: Pest test files
- `database/`: Migrations, factories, and seeders
- `config/`: Laravel configuration files
- `public/`: Publicly accessible assets

## Key Commands
- **Install dependencies**: `composer install` & `npm install`
- **Development server**: `npm run dev` (Runs Laravel Serve, Vite, Queue Listener, and Pail concurrently)
- **Build assets**: `npm run build`
- **Linting & Formatting**: `npm run lint`, `npm run format`, `npm run lint:check`, `npm run types:check`
- **Testing**: `composer test`
