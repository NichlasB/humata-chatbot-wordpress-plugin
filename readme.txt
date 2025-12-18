=== Humata Chatbot ===
Contributors: alynt
Tags: chatbot, ai, humata, chat, knowledge base
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat interface that connects to your Humata knowledge base, allowing users to ask questions and get instant answers from your documents.

== Description ==

Humata Chatbot creates a beautiful, modern chat interface on your WordPress site that connects to your Humata AI knowledge base. Users can ask questions and receive intelligent answers based on the documents stored in your Humata folder.

= Features =

* **Three Display Modes**: Replace your homepage, create a dedicated chat page, or embed anywhere using a shortcode
* **Modern UI**: Clean, responsive design inspired by ChatGPT with smooth animations
* **Dark/Light Mode**: Automatic system preference detection with manual toggle
* **Conversation History**: Chat history persists in browser storage
* **Rate Limiting**: Built-in protection against API abuse
* **Mobile Responsive**: Works perfectly on all screen sizes
* **Secure**: API keys are never exposed to the frontend

= Display Options =

1. **Homepage Override**: Replace your WordPress homepage with the chat interface
2. **Dedicated Page**: Create a chat page at a custom URL (e.g., /chat)
3. **Shortcode**: Embed the chat anywhere using `[humata_chat]`

= Requirements =

* A Humata account with API access
* Your Humata API key
* A Humata folder ID containing your documents

== Installation ==

1. Upload the `humata-chatbot` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Humata Chatbot
4. Enter your Humata API key and Folder ID
5. Choose your preferred display mode
6. Save settings

== Frequently Asked Questions ==

= Where do I get my Humata API key? =

You can obtain your API key from your Humata account settings at app.humata.ai.

= How do I find my Folder ID? =

The Folder ID can be found in the URL when viewing a folder in Humata, or in your Humata account settings.

= Can I customize the chat interface colors? =

The plugin uses CSS variables for theming. Developers can override these variables in their theme's CSS to customize colors.

= Does the chat history persist? =

Yes, chat history is stored in the user's browser localStorage and will persist until cleared.

= Is the API key secure? =

Yes, the API key is stored in your WordPress database and is never exposed to the frontend. All API calls are made server-side through WordPress.

= Can I use the shortcode multiple times on a page? =

The shortcode should only be used once per page to avoid conflicts.

== Screenshots ==

1. Chat interface in light mode
2. Chat interface in dark mode
3. Admin settings page
4. Mobile responsive view

== Changelog ==

= 1.0.0 =
* Initial release
* Three display modes: homepage, dedicated page, shortcode
* Dark/light mode with auto-detection
* Conversation history in localStorage
* Rate limiting per IP address
* Mobile responsive design
* Secure API key handling

== Upgrade Notice ==

= 1.0.0 =
Initial release of Humata Chatbot.

== Privacy ==

This plugin sends user messages to the Humata API (app.humata.ai) for processing. Please review Humata's privacy policy at https://www.humata.ai/privacy for information about how your data is handled.

The plugin stores:
* API credentials in your WordPress database (wp_options table)
* Rate limiting data using WordPress transients
* Chat history in users' browser localStorage (client-side only)

No personal data is collected or stored on your server beyond what is necessary for the plugin to function.
