# RRZE Answers

**RRZE Answers** is a plugin that offers you FAQ, glossaries and synonyms. You can receive these from other websites within the FAU network and the plugin keeps them up to date by an automatical sychronization.
The plugins comes with Shortcode, block and a widget.

---

## Key Features

- **awesome**  
  This feature is what you've always dreamt of.


- **Internationalization (i18n) for PHP and JS**  
  All user-facing strings are translatable. The build process and scripts support extracting and generating `.pot`, `.po`, `.mo`, and `.json` files for both PHP and JS translations.

---

## Directory Structure

```
rrze-answers/
│
├── blocks/
│   ├── block-dynamic/
│   │   ├── README.md
│   │   └── src/
│   └── block-static/
│       ├── README.md
│       └── src/
├── build/
│   └── blocks/
│       ├── block-dynamic/
│       └── block-static/
├── includes/
│   ├── Defaults.php
│   ├── Main.php
│   └── Common/
│       ├── Blocks/
│       ├── CPT/
│       ├── Plugin/
│       ├── Settings/
│       └── Shortcode/
├── languages/
│   └── rrze-answers.pot
├── package.json
├── rrze-answers.php
├── README.md
├── readme.txt
├── build-plugin.js
├── webpack.config.js
```

---

## Development Workflow

1. **Install dependencies:**
   ```sh
   npm install
   ```

2. **Build all blocks:**
   ```sh
   npm run build
   ```
   Each block will have its own build directory with compiled assets and static files.

3. **Internationalization:**
   - Extract PHP and JS strings:
     ```sh
     wp i18n make-pot . languages/rrze-plugin-blueprint.pot --domain=rrze-plugin-blueprint --exclude=node_modules,vendor,build
     ```
   - Generate JS translation JSON files:
     ```sh
     wp i18n make-json languages/rrze-plugin-blueprint-LOCALE.po --no-purge
     ```

---

## Customization

- **Register a new block:**  
  Add a folder under `blocks/`, create a `src/` and `block.json`, and add a build script in `package.json`.

- **Override default values:**  
  Edit `Defaults.php` or use the `rrze_plugin_blueprint_defaults` filter.

- **Change the PHP namespace:**  
  You can update the PHP namespace throughout the plugin by running:
  ```sh
  npm run update:namespace
  ```
  This will replace the namespace in all PHP files (except in the `build/` directory).

- **Change the text domain**
  You can update the text domain throughout the plugin by running:
  ```sh
  npm run update:textdomain
  ```
  This will replace the textdomain in all PHP and JS files (except in the `build/` directory).

- **Change the plugin slug**
  You can update the plugin slug throughout the plugin by running:
  ```sh
  npm run update:slug
  ```
  This will replace the plugin slug in all PHP and JS files (except in the `build/` directory).

  Note: Don’t forget to change the plugin directory and file names accordingly.

---

## Internationalization

- All strings use the `rrze-plugin-blueprint` text domain.
- Translation files (`.pot`, `.po`, `.mo`, `.json`) are in the `languages/` directory.
- JS translations are loaded using `wp_set_script_translations()`.

---

Clone, customize, and start building professional WordPress plugins with RRZE Plugin Blueprint!