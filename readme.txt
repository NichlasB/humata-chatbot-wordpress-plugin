=== Humata Chatbot ===
Contributors: alynt
Tags: chatbot, ai, humata, chat, knowledge base, claude, anthropic, straico
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat interface that connects to your Humata knowledge base with optional second-stage LLM processing, bot protection, and extensive customization options.

== Description ==

Humata Chatbot creates a beautiful, modern chat interface on your WordPress site that connects to your Humata AI knowledge base. Users can ask questions and receive intelligent answers based on your documents. Optionally route responses through a second AI (Straico or Anthropic Claude) for refinement.

= Core Features =

* **Three Display Modes**: Replace your homepage, create a dedicated chat page, or embed anywhere using a shortcode
* **Modern UI**: Clean, responsive design inspired by ChatGPT with smooth animations
* **Dark/Light Mode**: Automatic system preference detection with manual toggle
* **Conversation History**: Chat history persists in browser storage
* **PDF Export**: Export chat conversations to PDF with one click
* **Mobile Responsive**: Works perfectly on all screen sizes
* **Secure**: API keys are never exposed to the frontend

= AI Provider Options =

* **Humata AI**: Primary knowledge base powered by your uploaded documents
* **Second-Stage LLM Processing**: Optionally refine Humata responses through:
  * **Straico**: Access multiple AI models through a single API
  * **Anthropic Claude**: Including Claude 3.5 Sonnet with extended thinking mode
* **Custom System Prompts**: Configure AI behavior for both Humata and second-stage processing

= Security Features =

* **Rate Limiting**: Configurable per-IP rate limiting (requests per hour)
* **Cloudflare Turnstile**: Bot protection with managed, always-visible, or invisible widget modes
* **Max Prompt Length**: Configurable character limit (1-100,000) with live counter
* **Secure API Handling**: All API keys stored server-side, never exposed to frontend

= Display & Customization =

* **Custom Avatars**: Upload custom user and bot avatar images (32-64px configurable)
* **Configurable Disclaimers**: Bot response disclaimer, medical disclaimer, footer copyright
* **SEO Control**: Option to allow or block search engine indexing
* **Theme Options**: Light, dark, or auto (system preference)

= Auto-Links System =

* **Inline Auto-Links**: Automatically convert phrases in bot responses to hyperlinks
* **Intent-Based Resource Links**: Keyword-triggered pill-shaped resource links below responses
  * Supports external links and inline accordions
  * Case-insensitive whole-word matching
  * Automatic URL deduplication

= Floating Help Menu =

A site-wide floating help button with customizable content:

* External links section
* FAQ modal with accordion items
* Contact modal with custom HTML content
* Social media links (Facebook, Instagram, YouTube, X/Twitter, TikTok)
* Custom footer text

= Trigger Pages =

Create modal-based information pages accessible from the chat interface with custom titles, link text, and rich content.

= Display Options =

1. **Homepage Override**: Replace your WordPress homepage with the chat interface
2. **Dedicated Page**: Create a chat page at a custom URL (e.g., /chat)
3. **Shortcode**: Embed the chat anywhere using `[humata_chat height="600px"]`

= Admin Settings =

Organized into 8 intuitive tabs:

1. **General**: API key, document IDs, system prompt
2. **Providers**: Second-stage LLM configuration (Straico/Anthropic)
3. **Display**: Location, theme, SEO, disclaimers, avatars
4. **Security**: Rate limiting, max prompt chars, Cloudflare Turnstile
5. **Floating Help**: Site-wide help menu configuration
6. **Auto-Links**: Inline and intent-based link rules
7. **Pages**: Trigger pages for modal content
8. **Usage**: Shortcode reference and chat page URL

= Requirements =

* A Humata account with API access
* Your Humata API key
* Document IDs from your Humata account
* (Optional) Straico API key for second-stage processing
* (Optional) Anthropic API key for Claude integration
* (Optional) Cloudflare Turnstile site/secret keys for bot protection

== Installation ==

1. Upload the `humata-chatbot` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Humata Chatbot
4. In the **General** tab, enter your Humata API key and Document IDs
5. In the **Display** tab, choose your preferred display mode and theme
6. (Optional) Configure security settings in the **Security** tab
7. (Optional) Set up second-stage LLM processing in the **Providers** tab
8. Save settings

== Frequently Asked Questions ==

= Where do I get my Humata API key? =

You can obtain your API key from your Humata account settings at app.humata.ai.

= How do I find my Document IDs? =

Document IDs can be found in your Humata account. You can enter multiple IDs separated by commas.

= What is second-stage LLM processing? =

This optional feature routes Humata's response through a second AI (Straico or Anthropic Claude) for refinement. This can improve response quality, add formatting, or apply custom instructions via a system prompt.

= How do I set up Cloudflare Turnstile? =

1. Create a Turnstile widget at dash.cloudflare.com
2. Copy your Site Key and Secret Key
3. Go to Settings > Humata Chatbot > Security tab
4. Enable Turnstile and paste your keys
5. Choose your preferred widget appearance (managed, always, or invisible)

= What are Auto-Links? =

Auto-Links automatically convert specific phrases in bot responses into clickable hyperlinks. Intent-Based Links show pill-shaped resource buttons below responses when certain keywords are detected in the user's question.

= How does the Floating Help Menu work? =

When enabled, a floating "Help" button appears on all pages of your site. It can contain external links, FAQ accordions, contact information, and social media links.

= Can I customize the chat interface colors? =

The plugin uses CSS variables for theming. Developers can override these variables in their theme's CSS to customize colors.

= Does the chat history persist? =

Yes, chat history is stored in the user's browser localStorage and will persist until cleared.

= Is the API key secure? =

Yes, all API keys are stored in your WordPress database and are never exposed to the frontend. All API calls are made server-side through WordPress.

= Can I use the shortcode multiple times on a page? =

The shortcode should only be used once per page to avoid conflicts.

= How do I export a chat conversation? =

Click the PDF export button in the chat header to download the current conversation as a PDF file.

== Screenshots ==

1. Chat interface in light mode
2. Chat interface in dark mode
3. Admin settings - General tab
4. Admin settings - Providers tab
5. Admin settings - Security tab with Turnstile
6. Floating help menu
7. Mobile responsive view
8. PDF export feature

== Changelog ==

= 1.1.0 =
* Added second-stage LLM processing with Straico and Anthropic Claude support
* Added Anthropic Claude extended thinking mode
* Added Cloudflare Turnstile bot protection
* Added configurable max prompt character limit with live counter
* Added PDF export functionality using bundled jsPDF
* Added custom avatar images for user and bot (32-64px configurable)
* Added bot response disclaimer box
* Added medical disclaimer with footer link
* Added footer copyright text option
* Added SEO indexing control
* Added inline auto-links for bot responses
* Added intent-based resource links with keyword matching
* Added floating help menu with external links, FAQ, contact modal, and social links
* Added trigger pages for modal-based content
* Added message actions: copy, regenerate, and edit buttons
* Added scroll toggle button for chat navigation
* Reorganized admin settings into 8 intuitive tabs
* Improved rate limiting with configurable requests per hour
* Improved error handling with user-friendly messages
* Added system prompt configuration for Humata queries

= 1.0.0 =
* Initial release
* Three display modes: homepage, dedicated page, shortcode
* Dark/light mode with auto-detection
* Conversation history in localStorage
* Rate limiting per IP address
* Mobile responsive design
* Secure API key handling

== Upgrade Notice ==

= 1.1.0 =
Major feature update with second-stage LLM processing, Cloudflare Turnstile protection, PDF export, custom avatars, auto-links, floating help menu, and extensive customization options.

= 1.0.0 =
Initial release of Humata Chatbot.

== Privacy ==

This plugin sends user messages to external AI services for processing:

* **Humata API** (app.humata.ai) - Primary knowledge base queries
* **Straico API** (optional) - Second-stage LLM processing
* **Anthropic API** (optional) - Claude integration for second-stage processing
* **Cloudflare Turnstile** (optional) - Bot verification

Please review each service's privacy policy for information about how your data is handled.

The plugin stores:
* API credentials in your WordPress database (wp_options table)
* Rate limiting data using WordPress transients
* Plugin settings in wp_options table
* Chat history in users' browser localStorage (client-side only)

No personal data is collected or stored on your server beyond what is necessary for the plugin to function.
