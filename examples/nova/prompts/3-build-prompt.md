Build the Nova Güzellik website on my WordPress test site through the "Nova" connector (FB AI Engine – Claude Connector). The attached file **Nova-build-kit.md** has everything: the build plan, images, theme settings, CSS, form, footer, menu, every page and every blog post as ready block markup. I already approved the mockups in chat.

The site is fresh: only the connector is installed. Work in this order and keep me posted live:

0. **task_status** at the start with all steps below (and a short spoken "brief"), at every step, and with done=true at the end. I watch on Watch Me Live with voice on.
1. **build_plan_submit** with kit section 1 exactly. Tell me it is waiting, wait until I approve it, then check **build_plan_status**.
2. **plugins_install** `wpvivid-backuprestore` → **backup_create** → wait until **backup_status** shows a fresh backup.
3. **theme_install** `kadence`.
4. **plugins_install** `contact-form-7`.
5. **content_trash** id 1 ("Hello world!").
6. **media_upload** every image in kit section 2 (url + alt). Keep a list of key → id + url.
7. **custom_css_set** with kit section 4 (mode replace). Then **theme_settings_get**, and set the Kadence palette, fonts, page layout, logo, header and header button with **theme_settings_set** (kit section 3), keeping Kadence's exact structures. If a header part cannot be set through theme settings, link your browser first (the connector tells you how) and use the Customizer.
8. **form_create** with kit section 5 and keep the shortcode.
9. Pages: for each page in kit section 8, **content_create_draft** (post_type page, title exactly as given, content exactly as given, with `{{img:KEY}}` replaced by the uploaded url and `{{form_shortcode}}` by the shortcode), then **content_publish**.
10. Blog posts: for each post in kit section 9, **content_create_draft** (post_type post, with the excerpt), **post_settings_set** (category + featured image id), then **content_publish**. Never publish a post without its featured image.
11. **menu_set** (kit section 7, primary + mobile). **site_settings**: front page = Anasayfa, title "Nova Güzellik", tagline "Estetik, kozmetoloji ve dermatoloji kliniği". **widgets_set** footer1–footer4 (kit section 6, replace `{{img:logo}}`).
12. **site_check**. Fix every problem it finds, then run it again.
13. Finish with task_status done=true and a brief: what was built, the page links, and anything left for me (the placeholders: phone, email, opening hours, map).

Rules: follow the kit's markup and texts exactly. Don't add <style> tags to pages. Ask me before anything outside the plan. If a step fails, tell me the exact error and the step number before trying something else.
