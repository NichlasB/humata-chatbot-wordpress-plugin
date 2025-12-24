# Humata Chatbot

A WordPress plugin that creates an AI-powered chat interface connecting to your Humata knowledge base, with optional second-stage LLM processing via Straico or Anthropic Claude.

## Features Overview

- **Chat Interface**: Modern, responsive UI with dark/light mode
- **Multiple Display Modes**: Homepage override, dedicated page, or shortcode embed
- **Second-Stage LLM**: Route responses through Straico or Anthropic Claude for refinement
- **Security**: Rate limiting, Cloudflare Turnstile, max prompt length
- **Customization**: Custom avatars, disclaimers, auto-links, floating help menu
- **Export**: PDF export of chat conversations

## Architecture

```
humata-chatbot/
├── assets/
│   ├── css/              # Source CSS (chat-widget, floating-help)
│   ├── js/               # Source JS + vendor libs (jsPDF)
│   ├── src/              # Modular source (esbuild entry points)
│   │   ├── admin/        # Admin settings JS/CSS
│   │   ├── chat-widget/  # Chat widget modules
│   │   └── floating-help/# Floating help modules
│   └── dist/             # Built assets (auto-generated)
├── includes/
│   ├── Admin/            # Admin settings helpers
│   │   ├── Render/       # Field rendering traits
│   │   ├── Settings/     # Settings schema, sanitizers
│   │   └── Ajax.php      # AJAX handlers
│   ├── Rest/             # REST API components
│   │   ├── Clients/      # Straico, Anthropic clients
│   │   └── *.php         # Rate limiter, Turnstile, SSE parser
│   ├── admin-tabs/       # Settings tab classes
│   └── class-*.php       # Main plugin classes
├── templates/            # PHP templates (chat-interface.php)
└── humata-chatbot.php    # Plugin entry point
```

## Admin Settings Tabs

| Tab | Options |
|-----|---------|
| **General** | API key, document IDs, system prompt |
| **Providers** | Second-stage LLM (Straico/Anthropic), model selection, extended thinking |
| **Display** | Location, theme, SEO indexing, disclaimers, avatars |
| **Security** | Rate limit, max prompt chars, Cloudflare Turnstile |
| **Floating Help** | Enable, external links, FAQ, contact modal, social links |
| **Auto-Links** | Inline phrase→URL rules, intent-based keyword→links |
| **Pages** | Trigger pages (modal content) |
| **Usage** | Shortcode reference, chat page URL |

## REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/humata-chat/v1/ask` | POST | Send message, get AI response |
| `/wp-json/humata-chat/v1/clear-history` | POST | Clear server-side conversation |

---

# Developer Notes

The PHP runs entirely in WordPress — the build tooling below is **developer-only** and bundles frontend/admin assets into `assets/dist/`.

## Quick Start

1) Install dependencies (first time only):

```bash
npm install
```

2) Run a production build (updates `assets/dist/*` once):

```bash
npm run build
```

3) Development watch mode (auto rebuild on save):

```bash
npm run dev
```

## VS Code / Cursor Auto-Build

This repo includes a VS Code task in `.vscode/tasks.json` that automatically starts `npm run dev` when you open the folder. The watcher rebuilds `assets/dist/*` whenever you save files under `assets/src/*`.

If VS Code prompts you to allow automatic tasks, click **Allow**.

## Pre-Commit Hook

We use Husky to run `npm run build` on every commit and automatically stage `assets/dist/*`.

After `npm install`, Husky is installed via the `prepare` script. If needed:

```bash
npm run prepare
```

## Deploying to WordPress

Upload the plugin folder to `/wp-content/plugins/`.

- **Do include**: `assets/dist/*`, `assets/js/vendor/*`
- **Do not include**: `node_modules/`

## Key Files

| File | Purpose |
|------|---------|
| `humata-chatbot.php` | Plugin entry, activation hooks, constants |
| `includes/class-admin-settings.php` | Admin settings page, traits loader |
| `includes/class-rest-api.php` | REST endpoints, Humata/Straico/Anthropic calls |
| `includes/class-template-loader.php` | Template loading, shortcode, floating help |
| `includes/Admin/Settings/Schema.php` | Tab→options mapping |
| `templates/chat-interface.php` | Full-page chat template |

## CSS Variables

The chat widget uses CSS custom properties for theming. Override in your theme:

```css
:root {
  --humata-primary: #your-color;
  --humata-bg: #your-bg;
  /* etc. */
}
```






