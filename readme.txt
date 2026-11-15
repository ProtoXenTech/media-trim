=== Media Trim ===
Contributors: protoxen
Donate link: https://protoxen.com/
Tags: media, media cleaner, clean images, unused images, image cleaner
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-safe, high-performance unused media detector, quarantine manager, and disk space optimizer for WordPress.

== Description ==

**Media Trim** is a safety-first, high-performance media cleaner and disk space optimizer crafted specifically for WordPress administrators, agencies, and performance purists.

Over time, WordPress sites accumulate gigabytes of forgotten, unused, and duplicate media assets that bloat backups and consume expensive hosting storage. Existing media cleaners often act like blind wrecking balls—permanently deleting assets that were actually used in page builders or theme headers, leaving sites broken.

**Media Trim solves this with a deterministic, two-step quarantine safety architecture.**

### 🛡️ Why Media Trim is Different

* **Safety-First Quarantine (Never Instant Delete)**: Flagged unused files are staged into an isolated quarantine folder with security protection. If you ever notice an issue, you can restore any file with **1 click**.
* **Zero False-Positive Scanner**: Cross-references six distinct storage layers:
  1. Featured post thumbnails (`_thumbnail_id`)
  2. Gutenberg block attributes and classic post content
  3. Theme customizer site logos, favicons, headers, and backgrounds
  4. WooCommerce product galleries
  5. Page builder storage (Elementor and Divi)
  6. User profile avatars
* **Asynchronous Chunked Processing**: Scans files in non-blocking batches of 50 via AJAX with a smooth live progress bar. Zero memory exhaustion or 504 server gateway timeouts.
* **0 KB Frontend Weight**: 100% administrative tool. Zero scripts or stylesheets load on visitor-facing pages.
* **Asset Whitelisting**: One-click whitelist toggle so important assets are permanently excluded from scans.

== Installation ==

1. Upload the `media-trim` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins dashboard.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Tools > Media Trim** to run your first safe media scan.

== Frequently Asked Questions ==

= Will Media Trim break my website by deleting images? =
No! Media Trim never permanently deletes files directly. When you choose to quarantine unused media, files are moved into a staging directory. You can test your site and either restore them with 1 click or permanently purge them when you are 100% confident.

= Does it scan Elementor and Gutenberg blocks? =
Yes. Media Trim checks block markup, image IDs, and serialized page builder payloads (including Elementor) to ensure any image placed in a layout is recognized as active.

= Does it load anything on the front-end? =
No. Media Trim is completely restricted to the WordPress Admin dashboard under Tools. It adds 0 KB of JavaScript or CSS to your public site.

== Screenshots ==

1. Media Trim Dashboard with reclaimable space insights and live batch scan.
2. Interactive Media Inspector with quarantine and restore controls.

== Changelog ==

= 1.0.0 =
* Initial public release.
