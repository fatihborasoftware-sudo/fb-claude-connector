# Changelog

## 1.4.0 (25 October 2026)

Claude can now finish a whole site build by itself, including block themes and a WooCommerce shop. Before, the owner still had to click through a few screens at the end.

- **content_update** also sets a page's **slug** (its address, e.g. `magaza`) and its **page template** (e.g. a block theme's *Full width page*). Renaming a page to a title in the build plan (for example WooCommerce's *Shop* to *Mağaza*) makes it part of the plan.
- **site_settings** also sets the **posts page** (Settings → Reading) and the **site logo** (the Site Logo block and the theme logo).
- **menu_set** works on **block themes**. They have no menu locations, so Claude writes the site Navigation that the header's Navigation block shows. Dropdowns work too.
- **theme_settings_get** says whether the theme is a block theme and lists its page templates.
- **theme_install** and **plugins_install** also install from a **zip address** (`zip_url`, https), for example a GitHub release or a `dist/` zip. A zip replaces an installed copy, so Claude can install themes and plugins that are not on WordPress.org, and can update this connector itself after you approve it.
- New **WooCommerce** tools: **woo_products_list**, **woo_product_set** (create or update a product with prices, sale price, texts, images and categories) and **woo_settings_set** (currency and price format, store country, store pages, and a shipping zone with free shipping over an amount and a flat rate). A build plan covers them when it lists `"shop": true`.
- A new build plan no longer drops work that is still waiting for **Launch**: it moves to the new plan.

## 1.3.3 (21 October 2026)

- **Watch Me Live is now a design checker.** Three new buttons above the preview show your site the way it looks on a **desktop**, a **tablet** (768 px) or a **phone** (390 px).
- **Send to Claude.** Click it, drag a box around the part that looks wrong (like the snipping tool on your PC), write what should change and press Send. Claude gets the picture, plus which Elementor widget you boxed, so it can fix exactly that. The first time, your browser asks whether it may capture this tab: choose this tab and allow it. You can also send the whole page in the current view, or upload a picture from your phone.
- What you sent, and Claude's answer, appear under **Sent to Claude** on the Now panel.
- New Claude tools: **feedback_list** (read what you sent) and **feedback_done** (mark it fixed, with a short answer you see in Watch Me Live). Pictures are kept privately in uploads/fbcc-feedback (the last 30), not in the media library.

## 1.3.2 (21 October 2026)

- **page_screenshot** — Claude can now look at a page the way a visitor sees it, on a phone (the default), a tablet or a desktop, and check its own work by eye after a change. You no longer have to send screenshots. The picture is made by the free WordPress.com mShots service, so only public pages work, and a brand-new picture can take up to half a minute.

## 1.3.1 (21 October 2026)

- The approved build plan now covers the Elementor tools too. **elementor_set** on a published page listed in the plan, and **elementor_kit_set** when the plan includes the theme, run at once instead of waiting in Approvals, just like content_update and custom_css_set already did. Anything outside the plan still goes to approval.

## 1.3.0 (21 October 2026)

- **elementor_get / elementor_set** — Claude reads and writes a page's real Elementor widgets: the whole page, an Elementor template export, or small patches by element id (set, delete, insert_after, append). The owner keeps editing the result in Elementor. The last 5 versions are kept per page with an undo link. Drafts change at once; published pages go to approval.
- **elementor_kit_get / elementor_kit_set** — read and change Elementor Site Settings (global colours, fonts, button styles). Changes always go to approval and keep an undo copy.
- **page_html** — read a page the way visitors get it (after Elementor, shortcodes, snippets and plugins) or a CSS/JS file of the site, with "find" to return only the parts around a text or class name.
- **snippets_list / snippet_get** — read the Code Snippets plugin's snippets (read-only).

## 1.2.4 (3 October 2026)

- Watch Me Live follows Claude inside the Elementor editor. The follow script also loads on Elementor's editor screen, and there it mirrors the page being built (the preview) with an "Elementor editor" label, instead of the editor itself.

## 1.2.3 (3 October 2026)

- Fix: `backup_create` said "WPvivid is not active" on every site even though WPvivid was installed and active. WPvivid only loads its backup interface inside wp-admin, and Claude's requests are REST requests, not wp-admin; the connector now loads that interface itself. If it still cannot, the error says so and points to WPvivid's own *Backup Now*.

## 1.2.2 (3 October 2026)

- Fix: the connector's settings now have their own name (`fbcc_connector_settings`). Before, they shared `fbcc_settings` with other plugins that shorten their name to "fbcc" (for example a cookie-consent plugin). On such sites the permission level never saved and stayed **Read-only**. Existing connector settings move over automatically on the first load; another plugin's settings are never touched.

## 1.2.1 (3 October 2026)

- form_create is always available; when Contact Form 7 is missing it says to install `contact-form-7` first. A build chat that installs CF7 midway can now make the form without starting a new chat.

## 1.2.0 — build from a blank WordPress (3 October 2026)

- **theme_install** — installs a free theme from WordPress.org and activates it; the previous theme can be switched back with the undo link.
- **content_trash** — moves a page or post to the Trash (restorable), e.g. the default "Hello world!" and "Sample Page". Never the homepage or the posts page.
- Build plans can list **themes** and items to **trash**. Installs the owner approved in the plan run even while the lab lock is on; the AI Engine emergency lock and the Online Lab lock still block everything.
- Setup and Overview warn when the site uses Plain links (the connector address does not work then) and offer a one-click *Use post-name links*.
- Without WPvivid, backup_create and backup_status tell Claude to install `wpvivid-backuprestore` first. Claude's instructions include the fresh-site order: WPvivid → backup → theme → plugins → clean-up → build.

## 1.1.5

- Fix: the Watch Me Live side panel keeps the width you drag it to.

## 1.1.4

- Watch Me Live: drag the side panel's left edge to resize it (300–640 px, double-click to reset); minimize it into a slim rail with the » button or the `]` key (brief, approvals with a pulsing count, feed, step, kill switch); **Light / Dark / Auto** theme. Width, minimized state and theme are remembered on this computer. Phones and small tablets keep the stacked layout.

## 1.1.3

- Watch Me Live: an orange glow around your site while Claude works; it fades out when the task is finished.

## 1.1.2

- Watch Me Live: a working pill next to LIVE with moving dots, what Claude is doing right now and a running clock; "Finished" and the total time at the end. The admin bar badge shows the moving dots and the running time too.

## 1.1.1 (3 October 2026)

- site_check compares a fresh copy of each page with what visitors actually get and reports pages where a CDN or page cache still serves an old version ("Visitors see an old cached copy" — purge the cache).

## 1.1.0 (3 October 2026)

- **backup_create** — Claude takes a full WPvivid backup itself (runs in the background); **backup_status** shows whether a backup is running and the result of the last one. Overview has a Backups card with "Take a backup now".
- **media_upload_data** — images Claude makes in chat go straight into the media library (base64, up to 6 MB), optionally as a page's featured image.
- **site_check** — opens every published page and post like a visitor and reports broken links and images, block/shortcode/CSS code showing as text, missing alt text, missing or repeated H1 and blog posts without a featured image. Overview has a Site check card.
- **Türkçe interface** — Claude Connection, Watch Me Live, the link page, the sign-in page and the admin bar badge in English or Turkish, per administrator (Auto follows the WordPress profile language).
- Fix: backup age is measured correctly whatever the site's time zone.

## 1.0.0 — first public release (3 October 2026)

- First public release on GitHub. Same code as 0.7.1, renumbered as version one.
- Includes everything below: approved build plans, Watch Me Live with Claude's browser and voice briefs (English / Türkçe), theme, widget, CSS, menu, media and post tools, approvals, undo, activity log and the kill switch.

## 0.7.1

- Polish after the live test: the "Claude is in" dock labels no longer wrap; the Link Claude’s browser page has a proper title (it showed as an empty page name in the activity log).

## 0.7.0

- Follow Claude's browser: Claude links its own browser (Claude Connection → Follow Claude's browser). Watch Me Live then shows that browser live — the page it is on, an orange marker on what it clicked, and a view-only copy of the screen. Only the linked browser reports; scripts, passwords, hidden fields and nonces are never copied. The link ends when the task is done, when you unlink, or after 2 hours. Browser steps appear in the activity log as "browser".
- Voice briefs: a start brief, each step, "waiting for you" and a finish brief, read aloud when the voice is on. Choose what to read, English or Türkçe, the voice and the speed; Repeat / Skip on the Now panel. task_status takes a new "brief" field.
- New tools: post_settings_set (featured image, categories, tags), media_list, backup_status (latest WPvivid backup), custom_css_set (Additional CSS, with undo); site_settings can set the site icon.
- Fixes: Kadence's global palette is now saved where Kadence reads it; inline SVG icons survive in content Claude writes; task steps stay visible between progress updates.
- Build plans also cover post settings of their posts and the site CSS.

## 0.6.0

- Theme tools: theme_settings_get / theme_settings_set (Kadence header, footer, colours, fonts) with undo; widgets_set for footer columns; plugins_install from WordPress.org (Site maintainer, lab lock respected); form_create for Contact Form 7.
- Build plans can include blog posts (type: post), the theme look (theme: true) and named plugins.

## 0.5.0

- Approved build plans: the owner approves the whole website plan once (build_plan_submit); inside the plan, publishing and editing its pages, its menu and its site settings run without further approvals. Go live automatically or with one Launch click. Anything outside the plan still asks. Plans expire after 14 days and can be ended at any time.
- New tools: build_plan_submit, build_plan_status.

## 0.4.1

- Change highlights now mark each changed block even when a page is wrapped in one Group block.

## 0.4.0

- Watch Me Live v2: light design in Claude orange, your website full size and clickable, browser controls, Following Claude / Browsing freely, change highlights on the page, 'Claude just updated' notice, 'Claude is in' dock, side panel with Now / Approve / Feed.

## 0.3.2

- Approve or reject straight from Watch Me Live (and 'Approve all in order', two-click confirm).

## 0.3.1

- Watch Me Live: big live preview (Stage + big preview / Preview only), refreshes after every change and approval.
- Approvals listed oldest first, so approving top-down follows Claude's order.
- All tools are always listed; the permission level is enforced when a tool is used (no reconnect after changing the level).

## 0.3.0

- Watch Me Live: full-screen live stage (Claude Connection → ▶ Watch Claude live), cursor jumps to the window of each tool call, live preview of Claude's latest page, feed, counters, optional voice.
- New tools: media_upload, menu_set (approval), site_settings (approval).
- Administrators can choose whether the connector obeys AI Engine's emergency and Online Lab locks (Permissions & tools).
