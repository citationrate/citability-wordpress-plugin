=== CitationRate AI Visibility ===
Contributors: citationrate
Tags: ai, seo, schema, json-ld, chatgpt
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI citability score in the Block Editor plus a guided JSON-LD wizard. Optimize content to be cited by ChatGPT, Gemini, Claude and Perplexity.

== Description ==

CitationRate AI Visibility measures how "citable" your WordPress content is by ChatGPT, Gemini, Claude, Perplexity and Google AI Overviews — giving each page a Citability Score right inside the Block Editor, as you write.

The plugin computes a 0–100 score locally from 33 on-page signals (heading structure, page description, length, readability, image alt text, internal/external links, schema markup, source citations and more) and offers a guided JSON-LD wizard to generate the correct Schema.org markup.

= Features =

* **Live score in the Block Editor** — a sidebar that updates as you type, no saving needed
* **5 macro-area breakdown** — Coherence, Identity, Content, Performance, Reputation, each with a plain-language explanation
* **Actionable suggestions** — a prioritized list of what to improve
* **Guided JSON-LD wizard** — 13 content types (Article, Blog post, News, FAQ, Tutorial, Recipe, Review, Video, Product, Service, Event, Course, Job posting). Fields are auto-filled from your content; you never edit raw JSON
* **Citation Rate estimate** — an illustrative projection of how often AI would cite the page
* **English and Italian** — the plugin follows your WordPress language

= Privacy-friendly, no server cost =

The lite score is computed entirely on your own WordPress. No external API calls and no data sent to third parties in the default mode.

== External services ==

In its default (lite) mode, CitationRate AI Visibility does **not** send any data to external services — the score is computed locally on your site.

The plugin links out to the CitationRate platform for optional features. These are plain links that open in a new tab when the user clicks them; the plugin itself does not transmit any data:

* "Complete your score" / "powered by" → https://suite.citationrate.com (the CitationRate web app)
* "Discover your Citation Rate" → https://avi.citationrate.com (the AI Visibility Index web app)

An optional CitationRate API key field is available in the settings for a future full-audit integration. Only if you enter a key and use that feature would page content be sent to the CitationRate backend for analysis. Without a key, nothing is sent.

CitationRate Privacy Policy: https://citationrate.com/privacy/

== Installation ==

1. Upload the `citationrate-ai-visibility` folder to `/wp-content/plugins/`, or go to Plugins → Add New → Upload Plugin and upload the ZIP.
2. Activate the plugin from the **Plugins** menu.
3. Open any post in the Block Editor: the Citability sidebar appears automatically.
4. (Optional) Go to **Settings → CitationRate AI Visibility** to connect a CitationRate account.

== Frequently Asked Questions ==

= Does this replace a full CitationRate audit? =

No. It is an on-page estimate based on 33 of the 56 full parameters. It does not include backlink analysis, the real AI citation rate, or live tests with AI models. For the full audit, connect a CitationRate account.

= Does the plugin send my content to third parties? =

No. In the default mode everything is computed locally. Content is only sent if you configure the optional CitationRate API key.

= Does it support Yoast and Rank Math? =

Yes. The plugin reads the page description and focus keyword from both, if installed.

= In which languages is it available? =

English and Italian. The plugin follows your WordPress language (Settings → General, or your user profile language).

== Screenshots ==

1. Citability sidebar in the Block Editor with the score and macro-area breakdown
2. Citation Rate panel: illustrative estimate and link to AVI
3. JSON-LD wizard: choose what the page is about, fields auto-filled
4. Actionable suggestions list
5. JSON-LD output injected into the public page source

== Changelog ==

= 0.3.2 =
* Renamed to "CitationRate AI Visibility".
* Hardened the inline JSON-LD output so values can never break out of the script tag.
* Validated the default schema setting against the supported types.
* Internationalization compliance: added translators comments and ordered placeholders.
* Housekeeping: rely on WordPress core to load translations.

= 0.3.0 =
* Internationalized: source strings in English (default) plus an Italian translation (it_IT). The language follows the WordPress setting.
* Updated the "Identity" macro-area description.

= 0.2.1 =
* Live score that updates on every edit, without saving.
* Citation Rate panel simplified.
* JSON-LD wizard: a simple "What is this page about?" question with 13 content types and auto-filled guided fields.
* Info tooltips on the score and on each macro-area.
* "Parameters analyzed" counter and a call to run the full audit for free on the CitationRate platform (UTM-tracked).

= 0.2.0 =
* New "Citation Rate" panel with an illustrative estimate and a link to AVI.

= 0.1.0 =
* First release: lite scorer, JSON-LD wizard, Block Editor sidebar.

== Upgrade Notice ==

= 0.3.2 =
Renamed to CitationRate AI Visibility, plus security, i18n and validation hardening.

= 0.3.0 =
Now available in English and Italian (follows your WordPress language).
