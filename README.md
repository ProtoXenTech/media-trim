# Media Trim

<p align="center">
  <img src="admin/images/banner-772x250.png" alt="Media Trim Banner" width="600">
</p>

An ultra-safe, high-performance unused media detector, quarantine manager, and disk space optimizer for WordPress. Crafted and maintained with love by [ProtoXen](https://protoxen.com/).

[![WordPress Plugin](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/plugins/media-trim/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE.txt)

---

## ⚡ Why Media Trim?

Over time, WordPress sites accumulate gigabytes of forgotten, unused, and duplicate media assets that bloat backups and consume expensive hosting storage. Existing media cleaners often act like blind wrecking balls—permanently deleting assets that were actually used in page builders or theme headers, leaving sites broken.

**Media Trim solves this with a deterministic, two-step quarantine safety architecture:**
- **Two-Step Quarantine Lifecycle**: Files are *never* deleted directly. They are moved into an isolated Quarantine staging state with **1-click instant restoration**.
- **Deterministic Reference Detection**: Scans core thumbnails, Gutenberg block attributes, Classic Editor HTML, WooCommerce galleries, theme customizer logos/favicons, and Elementor JSON without crashing memory.
- **Chunked Batch Processing Engine**: Non-blocking asynchronous batches of 50 items using WordPress REST API / AJAX workers.
- **0 KB Frontend Weight**: 100% administrative utility. Not a single byte is enqueued on visitor-facing pages.

---

## 🌟 Core Features

- **Reclaim Hosting Disk Space**: Calculate exact potential storage savings and reclaim hundreds of megabytes safely.
- **Multi-Vector Reference Scanning**: Inspects featured images, Gutenberg blocks, post content, WooCommerce product galleries, custom logos, and page builders.
- **Isolated Quarantine Staging**: Staged files are isolated into a secure directory protected by `.htaccess` and PHP silence.
- **1-Click Full Restore**: Mistake? Restore any file and its intermediate thumbnails back to its exact upload path instantly.
- **Asset Whitelisting**: Exclude vital branding or legal assets permanently from future scans.

---

## 🛠️ System Requirements
- **WordPress:** 6.0 or higher
- **PHP:** 7.4 or higher

---

## 🤝 Contributing
Issues and feature requests are welcome! Feel free to visit the [issues page](https://github.com/ProtoXenTech/media-trim/issues).

---

## 📄 License
Licensed under the [GNU General Public License v2.0 or later](LICENSE.txt).  
Crafted with pride by **[ProtoXen](https://protoxen.com/)**.
