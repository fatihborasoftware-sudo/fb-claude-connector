=== FB AI Engine – Claude Connector ===
Version: 1.1.1
Requires at least: 6.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets Claude connect to this WordPress site as a custom connector (remote MCP server, OAuth sign-in).

Endpoint: /wp-json/fbsa/v1/mcp
Discovery: /.well-known/oauth-protected-resource and /.well-known/oauth-authorization-server
Sign-in page: /fbcc-oauth/authorize (administrators only)

Safety
- Claude acts as the WordPress user "claude-agent" (Editor, cannot sign in).
- Permission levels: Read-only (default), Content editor, Site maintainer.
- Edits to live content, publishing and plugin updates always wait in the approval queue.
- Respects FBSA_EMERGENCY_WRITE_LOCK (no writes) and FBSA_ONLINE_LAB_ENABLED (lab lock always on).
- A revision is saved before every edit; the activity log links to it.
- Kill switch revokes every sign-in at once.

Tools: site_health, content_list, content_get, plugins_list, activity_recent, approvals_list, task_status,
content_create_draft, content_update, content_publish, plugins_update.
With FB Mind Map installed: mindmap_list, mindmap_get (read), mindmap_update (Content editor; mark_done, set_text, add_note, add_child; last 5 versions kept for undo).

Live status for widgets: GET /wp-json/fbsa/v1/claude/status (admins, X-WP-Nonce).

If the Setup → Server check fails and .htaccess is not writable, add this at the very top of .htaccess:

# BEGIN FB Claude Connector
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
<IfModule mod_setenvif.c>
SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1
</IfModule>
# END FB Claude Connector


0.3.0
- Watch Me Live: full-screen live stage (Claude Connection → ▶ Watch Claude live), cursor jumps to the window of each tool call, live preview of Claude's latest page, feed, counters, optional voice.
- New tools: media_upload, menu_set (approval), site_settings (approval).
- Administrators can choose whether the connector obeys AI Engine's emergency and Online Lab locks (Permissions & tools).

0.3.1
- Watch Me Live: big live preview (Stage + big preview / Preview only), refreshes after every change and approval.
- Approvals listed oldest first, so approving top-down follows Claude's order.
- All tools are always listed; the permission level is enforced when a tool is used (no reconnect after changing the level).

0.3.2
- Approve or reject straight from Watch Me Live (and 'Approve all in order', two-click confirm).

0.4.0
- Watch Me Live v2: light design in Claude orange, your website full size and clickable, browser controls, Following Claude / Browsing freely, change highlights on the page, 'Claude just updated' notice, 'Claude is in' dock, side panel with Now / Approve / Feed.

0.4.1
- Change highlights now mark each changed block even when a page is wrapped in one Group block.

0.5.0
- Approved build plans: the owner approves the whole website plan once (build_plan_submit); inside the plan, publishing and editing its pages, its menu and its site settings run without further approvals. Go live automatically or with one Launch click. Anything outside the plan still asks. Plans expire after 14 days and can be ended at any time.
- New tools: build_plan_submit, build_plan_status.

0.6.0
- Theme tools: theme_settings_get / theme_settings_set (Kadence header, footer, colours, fonts) with undo; widgets_set for footer columns; plugins_install from WordPress.org (Site maintainer, lab lock respected); form_create for Contact Form 7.
- Build plans can include blog posts (type: post), the theme look (theme: true) and named plugins.

0.7.0
- Follow Claude's browser: Claude links its own browser (Claude Connection → Follow Claude's browser). Watch Me Live then shows that browser live — the page it is on, an orange marker on what it clicked, and a view-only copy of the screen. Only the linked browser reports; scripts, passwords, hidden fields and nonces are never copied. The link ends when the task is done, when you unlink, or after 2 hours. Browser steps appear in the activity log as "browser".
- Voice briefs: a start brief, each step, "waiting for you" and a finish brief, read aloud when the voice is on. Choose what to read, English or Türkçe, the voice and the speed; Repeat / Skip on the Now panel. task_status takes a new "brief" field.
- New tools: post_settings_set (featured image, categories, tags), media_list, backup_status (latest WPvivid backup), custom_css_set (Additional CSS, with undo); site_settings can set the site icon.
- Fixes: Kadence's global palette is now saved where Kadence reads it; inline SVG icons survive in content Claude writes; task steps stay visible between progress updates.
- Build plans also cover post settings of their posts and the site CSS.

0.7.1
- Polish after the live test: the "Claude is in" dock labels no longer wrap; the Link Claude’s browser page has a proper title (it showed as an empty page name in the activity log).

1.0.0
- First public release on GitHub (same code as 0.7.1).

1.1.0
- backup_create: Claude takes a full WPvivid backup itself (runs in the background); backup_status now shows whether a backup is running and the result of the last one. Claude Connection → Overview has a Backups card with "Take a backup now".
- media_upload_data: images Claude makes in chat go straight into the media library (base64, up to 6 MB), optionally as a page's featured image.
- site_check: opens every published page and post like a visitor and reports broken links and images, block/shortcode/CSS code showing as text, missing alt text, missing or repeated H1 and blog posts without a featured image. Also a Site check card with "Run site check".
- Türkçe interface: Claude Connection, Watch Me Live, the link page, the sign-in page and the admin bar badge in English or Turkish — per administrator (Auto follows the WordPress profile language).
- Fix: backup age is now measured correctly whatever the site's time zone.

1.1.1
- site_check compares a fresh copy of each page with what visitors actually get and reports pages where a CDN or page cache still serves an old version ("Visitors see an old cached copy" — purge the cache).
