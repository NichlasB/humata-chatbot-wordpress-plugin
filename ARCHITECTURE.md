# Humata Chatbot – Architecture Guide

This document describes the file organization and conventions used in the Humata Chatbot WordPress plugin. AI assistants and contributors should follow these patterns when adding new features.

---

## Overview

The plugin connects WordPress to the Humata AI knowledge base, providing:
- A full-page chat interface (dedicated page, homepage replacement, or shortcode)
- Optional second-pass LLM review via Straico or Anthropic
- Cloudflare Turnstile bot protection
- Rate limiting and security features
- Floating help menu with FAQ/Contact modals
- Auto-linking and intent-based resource links

---

## Directory Structure

```
humata-chatbot/
├── humata-chatbot.php          # Main plugin entry point
├── uninstall.php               # Cleanup on uninstall
├── package.json                # Node/esbuild config
├── scripts/
│   └── build.mjs               # esbuild bundler script
├── assets/
│   ├── src/                    # Source files (edit these)
│   │   ├── admin/settings/     # Admin settings page JS/CSS
│   │   ├── chat-widget/        # Frontend chat widget modules
│   │   └── floating-help/      # Floating help menu JS/CSS
│   ├── dist/                   # Built output (do not edit)
│   ├── css/                    # Legacy CSS (being migrated)
│   └── js/                     # Legacy JS + vendor libs
├── includes/
│   ├── class-admin-settings.php    # Admin settings orchestrator
│   ├── class-rest-api.php          # REST API orchestrator
│   ├── class-template-loader.php   # Frontend template/enqueue
│   ├── Admin/                      # Admin-related traits/classes
│   ├── Rest/                       # REST API services
│   └── admin-tabs/                 # Settings tab classes
└── templates/
    └── chat-interface.php      # Chat UI template
```

---

## PHP Organization

### Core Classes (includes/)

| File | Purpose |
|------|---------|
| `class-admin-settings.php` | Orchestrates admin settings; uses traits for rendering/sanitizing |
| `class-rest-api.php` | Orchestrates REST endpoints; delegates to service classes |
| `class-template-loader.php` | Handles frontend template loading and asset enqueueing |

### Admin Traits & Classes (includes/Admin/)

Admin functionality is split into **traits** organized by concern:

```
includes/Admin/
├── Ajax.php                    # AJAX handlers (test API, fetch titles, etc.)
├── Render/                     # Field/section rendering methods
│   ├── Api.php                 # API key, document IDs fields
│   ├── Display.php             # Theme, location, avatar fields
│   ├── FloatingHelp.php        # Floating help menu fields
│   ├── Links.php               # Auto-links, intent-links repeaters
│   ├── Page.php                # Settings page shell, tab forms
│   ├── Providers.php           # Straico/Anthropic provider fields
│   ├── Sections.php            # Section description callbacks
│   └── Security.php            # Turnstile, rate limit fields
├── Settings/
│   ├── DocumentIds.php         # UUID parsing/sanitization
│   ├── Register.php            # register_setting() calls
│   ├── Schema.php              # Option schema definitions
│   └── Sanitize/               # Sanitization/validation
│       ├── Core.php            # Core sanitizers (second_llm_provider, etc.)
│       ├── FloatingHelp.php    # Floating help option sanitizer
│       ├── Links.php           # Auto-links/intent-links sanitizers
│       ├── Providers.php       # Anthropic model sanitizer
│       └── Security.php        # Turnstile appearance sanitizer
```

**Naming convention:** `Humata_Chatbot_Admin_Settings_{Category}_{Subcategory}_Trait`

### REST Services (includes/Rest/)

REST logic is split into **focused service classes**:

```
includes/Rest/
├── ClientIp.php                # Get client IP (static helper)
├── RateLimiter.php             # Transient-based rate limiting
├── TurnstileVerifier.php       # Cloudflare Turnstile verification
├── HistoryBuilder.php          # Build conversation context
├── SseParser.php               # Parse Humata SSE responses
└── Clients/
    ├── StraicoClient.php       # Straico API client
    └── AnthropicClient.php     # Anthropic Claude API client
```

**Naming convention:** `Humata_Chatbot_Rest_{ServiceName}`

### Settings Tabs (includes/admin-tabs/)

WooCommerce-style tabbed settings interface:

```
includes/admin-tabs/
├── class-humata-settings-tabs.php   # Tab controller
└── tabs/
    ├── class-tab-base.php           # Abstract base class
    ├── class-tab-general.php        # API settings tab
    ├── class-tab-providers.php      # Second LLM providers tab
    ├── class-tab-display.php        # Display/theme settings tab
    ├── class-tab-security.php       # Security settings tab
    ├── class-tab-floating-help.php  # Floating help menu tab
    ├── class-tab-auto-links.php     # Auto-links tab
    └── class-tab-usage.php          # Usage stats tab
```

---

## JavaScript Organization

### Source Modules (assets/src/)

JavaScript is modularized by feature:

```
assets/src/
├── admin/settings/
│   ├── index.js                # Admin settings entry point
│   └── style.css               # Admin settings styles
├── chat-widget/
│   ├── index.js                # Widget entry point (imports main.js)
│   ├── main.js                 # Core initialization & event binding
│   ├── config.js               # Configuration from wp_localize_script
│   ├── avatars.js              # Avatar display logic
│   └── style.css               # Widget styles
└── floating-help/
    ├── index.js                # Floating help entry point
    └── style.css               # Floating help styles
```

### Build Output (assets/dist/)

Built by `npm run build` using esbuild:
- `admin-settings.js` / `admin-settings.css`
- `chat-widget.js` / `chat-widget.css`
- `floating-help.js` / `floating-help.css`

**Never edit files in `assets/dist/`** – they are overwritten on build.

---

## Coding Conventions

### PHP

- **WordPress Coding Standards** for PHP
- **Guard clause first:** `defined( 'ABSPATH' ) || exit;`
- **Use traits** to compose functionality into main classes
- **Keep files lean:** Target ~100-300 lines; split when larger
- **DocBlocks:** Use `@since 1.0.0` for versioning
- **Naming:**
  - Classes: `Humata_Chatbot_{Category}_{Name}`
  - Traits: `Humata_Chatbot_{Category}_{Name}_Trait`
  - Functions: `humata_chatbot_{action}()`
  - Options: `humata_{setting_name}`

### JavaScript

- **ES6 modules** with named exports
- **No external frameworks** – vanilla JS only
- **Import from sibling modules:**
  ```js
  import { config } from './config';
  import { setAvatarContent } from './avatars';
  ```
- **Configuration via `wp_localize_script`:**
  ```js
  const config = window.humataConfig || {};
  ```

### CSS

- **BEM-style naming:** `.humata-{block}__element--modifier`
- **CSS custom properties** for theming (defined in style.css)
- **Mobile-first** responsive approach

---

## Adding New Features

### Adding a New Admin Setting

1. **Define the option** in `humata-chatbot.php` → `humata_chatbot_activate()`
2. **Register the setting** in `includes/Admin/Settings/Register.php`
3. **Add sanitizer** (if needed) in appropriate `includes/Admin/Settings/Sanitize/*.php`
4. **Add render method** in appropriate `includes/Admin/Render/*.php`
5. **Wire up in tab class** in `includes/admin-tabs/tabs/class-tab-*.php`

### Adding a New REST Endpoint

1. **Create service class** in `includes/Rest/` if new logic is needed
2. **Register route** in `class-rest-api.php` → `register_routes()`
3. **Add handler method** in `class-rest-api.php` (delegates to services)

### Adding a New JS Feature

1. **Create module** in `assets/src/chat-widget/` (or appropriate folder)
2. **Export functions** with named exports
3. **Import in entry point** (`index.js` or `main.js`)
4. **Run `npm run build`** to bundle

---

## Build Commands

| Command | Purpose |
|---------|---------|
| `npm run build` | Production build (minified) |
| `npm run dev` | Watch mode for development |

---

## Verification Checklist

After making changes:

1. ✅ Run `npm run build` – ensure no errors
2. ✅ Check `assets/dist/` outputs exist
3. ✅ Test admin page loads (settings tabs work)
4. ✅ Test chat interface loads (frontend works)
5. ✅ Check PHP lints on changed files

---

## Key WordPress Options

| Option Key | Type | Description |
|------------|------|-------------|
| `humata_api_key` | string | Humata API key |
| `humata_document_ids` | string | Comma-separated UUIDs |
| `humata_chat_location` | string | `dedicated`, `homepage`, or `shortcode` |
| `humata_chat_theme` | string | `light`, `dark`, or `auto` |
| `humata_rate_limit` | int | Requests per hour per IP |
| `humata_second_llm_provider` | string | `none`, `straico`, or `anthropic` |
| `humata_turnstile_enabled` | bool | Enable Cloudflare Turnstile |
| `humata_floating_help` | array | Floating help menu configuration |
| `humata_auto_links` | array | Auto-link rules |
| `humata_intent_links` | array | Intent-based link rules |

---

## REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/humata-chat/v1/ask` | POST | Send message to Humata |
| `/humata-chat/v1/verify-turnstile` | POST | Verify Turnstile token |
| `/humata-chat/v1/clear-cache` | POST | Clear rate limit transients |

---

*Last updated: December 2024*

