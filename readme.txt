=== Citability Score ===
Contributors: citationrate
Tags: ai, llm, seo, schema, json-ld, citationrate, ai overviews, chatgpt, gemini, perplexity
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Score di citabilità AI per ogni pagina + wizard JSON-LD (Article, FAQ, HowTo, Recipe, LocalBusiness). Ottimizza i contenuti per essere citati dagli LLM.

== Description ==

Citability Score misura quanto un contenuto WordPress è "citabile" da ChatGPT, Gemini, Claude, Perplexity e Google AI Overviews. Direttamente nel Block Editor, mentre scrivi.

Il plugin calcola in locale uno score 0-100 basato su ~18 segnali on-page (struttura heading, meta description, lunghezza, leggibilità, immagini con alt, link interni/esterni, schema markup, citazioni di fonti, ecc.) e offre un wizard JSON-LD assistito per generare il markup Schema.org corretto.

= Funzionalità =

* **Score live nel Block Editor**: barra laterale che si aggiorna mentre scrivi
* **Breakdown 5 macro-aree**: Coerenza, Identità, Contenuti, Prestazioni, Reputazione
* **Suggerimenti azionabili**: lista priorità di cosa migliorare
* **Wizard JSON-LD**: 5 schemi supportati (Article, FAQ, HowTo, Recipe, LocalBusiness)
* **Auto-populate**: il wizard riempie i campi dai dati del post
* **API key opzionale**: collega un account CitationRate per l'audit completo (oltre 50 parametri)

= Zero costi server =

Il calcolo dello score lite avviene interamente sul tuo WordPress. Nessuna chiamata API, nessun invio di dati a terzi (a meno di configurare l'API key opzionale).

== Installation ==

1. Carica la cartella `citability-score` in `/wp-content/plugins/`
2. Attiva il plugin dal menu **Plugin** in WordPress
3. Apri un articolo nel Block Editor: la sidebar Citability appare automaticamente
4. (Opzionale) Vai in **Impostazioni → Citability Score** per collegare un account CitationRate

== Frequently Asked Questions ==

= Lo score sostituisce un audit completo CitationRate? =

No. È una stima on-page basata su ~18 dei 56 parametri completi. Non include analisi backlinks, citation rate AI reale o test con modelli LLM. Per l'audit completo, collega un account CitationRate.

= Il plugin invia i miei contenuti a terzi? =

No, in modalità default tutto il calcolo avviene in locale. Solo se configuri l'API key CitationRate i contenuti vengono inviati al backend per l'audit completo.

= Supporta Yoast/RankMath? =

Sì. Il plugin legge meta description e focus keyword da entrambi se installati.

== Screenshots ==

1. Sidebar Citability nel Block Editor con score e breakdown
2. Wizard JSON-LD con scelta dello schema
3. Lista suggerimenti azionabili
4. Pagina impostazioni con API key

== Changelog ==

= 0.2.0 =
* Nuovo pannello "Citation Rate": spiega la formula (query citate ÷ query totali × 100), mostra una stima illustrativa basata sul Citability Score on-page e rimanda ad AVI per la misurazione reale.

= 0.1.0 =
* Prima release: scorer lite, JSON-LD wizard (5 schemi), Block Editor sidebar.

== Upgrade Notice ==

= 0.2.0 =
Aggiunto il pannello Citation Rate con stima illustrativa e collegamento ad AVI.

= 0.1.0 =
Prima release pubblica.
