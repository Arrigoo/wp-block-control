# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this plugin does

A WordPress plugin that adds a "CDP Segment control" panel to every Gutenberg block's inspector. Editors pick segments (from the Arrigoo CDP) that the block should be shown to or hidden from. All blocks still render server-side; a frontend script reads the visitor's segments from the Arrigoo CDP and either reveals or removes the marked blocks in the DOM.

## Commands

- `npm run build` — Builds both JS entry points with `@wordpress/scripts` (webpack) **and** copies `src/bundle.js` to `build/bundle.js`. The copy step is required because `bundle.js` is a pre-built CDP SDK artifact, not a webpack entry — webpack would otherwise overwrite or skip it.
- `npm start` — `wp-scripts start` (watch mode for `src/index.js` and `src/frontend-loader.js`). Note: this does **not** copy `bundle.js`; run `npm run build` once to seed it.
- `composer install` — Installs the `arrigoo/cdp-php-sdk` dependency and sets up the PSR-4 autoloader for the `Arrigoo\WpCdpBlockControl\` namespace. The plugin relies on a Composer autoloader being loaded by WordPress (see [README.md](README.md) — the deliciousbrains "WP in git" pattern).

No test suite or linter is configured.

## Architecture

### Two-stage rendering (the core trick)

1. **Server**: every block with selected segments is rendered with a `data-segments="seg1 seg2 !seg3"` attribute. A `<style>*[data-segments]{display:none}</style>` block in `<head>` hides them all by default ([EndUser.php:34-38](src_php/EndUser.php#L34-L38)).
2. **Client**: [frontend-loader.js](src/frontend-loader.js) waits for the CDP bundle to emit `ao_loaded`, reads the visitor's segment list from `window.argo.get('s')` (falling back to the base64-encoded `arrigoocdp` sessionStorage entry), then either unhides each block (`display: block`) or removes it from the DOM.

The `!` prefix means "hide from this segment". The literal segment `unknown` matches visitors the CDP has not recognized.

### PHP side (`src_php/`, namespace `Arrigoo\WpCdpBlockControl\`)

Hooks are registered in [index.php](index.php). Three classes, all static:

- **BlockControl** — registers the editor JS bundle (`build/index.js`) and injects `window.arrigooCdpSegments` via `wp_add_inline_script`. Adds a `selectedSegments` array attribute to every block via `register_block_type_args`. Owns the CDP segment fetch (cached 5 min in the `ARRIGOO_CDP` option).
- **EndUser** — emits the frontend `<style>`, `window.arrigooConfig`, and the loader script in `wp_head`.
- **AdminSettings** — Settings API page under Settings → Arrigoo CDP. Stores credentials and cookie-consent prefs in option `arrigoo_cdp_config`. Clearing settings deletes the segment cache.

**Config resolution order** (see `AdminSettings::get_config_value`): WP option → env var → PHP constant. Only `api_url`, `api_user`, `api_secret` participate in the fallback chain — they map to `CDP_API_URL` / `CDP_USER` / `CDP_API_KEY`. Cookie/frontend flags only come from the option.

### JS side (`src/`)

Three files, two webpack entries (see [webpack.config.js](webpack.config.js)):

- **`src/index.js`** (entry `index`) — Editor-side. Three `addFilter` calls:
  - `editor.BlockEdit` adds the `<InspectorControls>` panel with two `FormTokenField`s ("Only show to" and "Hide from"). The UI shows segment **titles** to editors but stores **sys_titles** in `selectedSegments`; hide entries are stored with a `!` prefix.
  - `blocks.registerBlockType` mirrors the PHP attribute registration so the editor knows about `selectedSegments`.
  - `blocks.getSaveContent.extraProps` writes `data-segments` (space-joined) onto the saved block markup. This is the contract the frontend loader reads.
- **`src/frontend-loader.js`** (entry `frontend-loader`) — Vanilla IIFE that wires up consent providers (`none` / `cookieinformation` / `cookiebot`) and gates `loadArrigooScript()` (which injects `bundleUrl`) on the chosen category. When `frontendScriptEnabled` is false under provider `none`, the loader still runs `processBlocks` (after a 300ms grace period) so segment markup is resolved even if the CDP script is loaded elsewhere (e.g., a tag manager).
- **`src/bundle.js`** — Minified Arrigoo CDP SDK, copied verbatim to `build/`. Not webpack-compiled. Exposes `window.argo` and dispatches `ao_loaded` / `ao_recognized`. Treat as a vendored binary — don't edit by hand.

### Data flow at a glance

```
Editor:   CDP API → BlockControl::get_segments (cached) → window.arrigooCdpSegments → FormTokenField
Save:     selectedSegments attr → data-segments="..." on block HTML
Render:   wp_head emits hide-all CSS + frontend-loader.js
Frontend: consent gate → bundle.js → window.argo.get('s') → show/remove [data-segments] elements
```

## Things to know when editing

- **The `build/` directory is committed.** Run `npm run build` and commit the output when changing JS — WordPress loads from `build/`, not `src/`.
- **Don't refactor `bundle.js`.** It's the externally-built CDP SDK. Replace it as a whole if it needs updating.
- **The PHP attribute registration and the JS attribute registration must stay in sync** ([BlockControl.php:41-50](src_php/BlockControl.php#L41-L50) and [src/index.js:199-216](src/index.js#L199-L216)). The server-side filter is what makes the attribute survive REST/render; the client-side one is what makes it available in the editor UI.
- **Cache invalidation**: `AdminSettings::sanitize_settings` deletes `ARRIGOO_CDP` on save. If you change how segments are fetched/shaped, also clear that option or bump the cache key, or stale data will hide for 5 minutes.
- **Segment string convention**: bare `sys_title` = show; `!sys_title` = hide; `unknown` / `!unknown` target visitors with no CDP profile. The show/hide split is enforced in the editor (selecting a segment on one side removes it from the other) but the frontend just applies whatever it reads from `data-segments`.
