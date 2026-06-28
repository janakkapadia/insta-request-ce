
## Tauri Updater on macOS
- **macOS Hidden Files**: macOS native tools add hidden `._` AppleDouble files when archiving. These will break the Tauri updater with an extraction error. Always use `export COPYFILE_DISABLE=1` before building the `.tar.gz` payload.
- **Signatures**: If a tarball payload is ever rebuilt or replaced, the signature in `update.json` MUST be updated with the contents of the new `.sig` file, otherwise the updater will fail with `The signature verification failed`.
- **Update Prompts**: `window.confirm` is silently blocked by Tauri's macOS WKWebView. You must use the native `@tauri-apps/plugin-dialog` `ask()` method for update prompts.
- **Plugin Capabilities**: When adding the dialog plugin, you must explicitly add `dialog:default` to the permissions array in `src-tauri/capabilities/default.json` and `production.json`.

## Tauri macOS WebKit (HTML5 Drag and Drop)
- **MIME Types**: WebKit natively drops custom MIME types (like `application/x-custom`) during internal drag operations. Always use `text/plain` combined with `JSON.stringify` to pass data securely.
- **Drag Source Requirements**: The CSS property `-webkit-user-drag: element;` must be explicitly added to `draggable="true"` elements, otherwise WebKit may ignore the drag start.
- **Drop Target Requirements**: Standard HTML5 requires preventing default on `dragover` to allow a drop. In Tauri/WebKit, you MUST also prevent the default behavior on `dragenter` (e.g. `@dragenter.prevent`), otherwise the browser will cancel the drag session entirely as soon as the element is entered.

## Deployments and Releases
- Always ask for explicit permission from the user before triggering any deployment (e.g. `dep deploy prod`) or creating any releases (e.g. `npm run release`). Never deploy or release automatically.

- Always ensure the main window `url` in `tauri.conf.json` is set to `/login` and not `/`.
