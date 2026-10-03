# Nova Güzellik — build kit for FB AI Engine – Claude Connector

Target site: **https://fatihbora.net/test** (connector "Nova", plugin 1.2.1+). Source site for images: https://fatihbora.net/botox. Approved mockups: https://claude.ai/artifact/54AFdTQrKdzjTDHQVg9fvM

Placeholders in the markup: `{{img:KEY}}` → the url media_upload returned for that key (section 2). `{{form_shortcode}}` → the shortcode form_create returned. Use every block exactly as written; do not add <style> tags (styles live in section 4).

## 1. Build plan (build_plan_submit arguments)
```json
{
 "title": "Nova Güzellik website",
 "summary": "Rebuild of fatihbora.net/botox as a modern Kadence site on the test install, from the approved Nova mockups: 8 pages, 6 blog posts, header, footer, menu, contact form. Images copied from the old site.",
 "mockups": "https://claude.ai/artifact/54AFdTQrKdzjTDHQVg9fvM",
 "pages": [
  {
   "title": "Anasayfa",
   "type": "page"
  },
  {
   "title": "Tedaviler",
   "type": "page"
  },
  {
   "title": "Fiyatlar",
   "type": "page"
  },
  {
   "title": "Öncesi ve Sonrası",
   "type": "page"
  },
  {
   "title": "Hakkımızda",
   "type": "page"
  },
  {
   "title": "Sık Sorulan Sorular",
   "type": "page"
  },
  {
   "title": "Blog",
   "type": "page"
  },
  {
   "title": "İletişim",
   "type": "page"
  },
  {
   "title": "Yaşsız Bir Cildin Sırları: Dermatoloji Uzmanlarından İpuçları",
   "type": "post"
  },
  {
   "title": "Akneye Elveda: Temiz ve Sağlıklı Bir Cilt İçin Çözümler",
   "type": "post"
  },
  {
   "title": "Cilt Bakımı İçerikleri: Etkili Ürünlerin Ardındaki Bilim",
   "type": "post"
  },
  {
   "title": "Güçlü ve Işıltılı Saçlar İçin Uzman İpuçları",
   "type": "post"
  },
  {
   "title": "Dermal Dolguların Çok Yönlülüğünü Keşfetmek",
   "type": "post"
  },
  {
   "title": "Saç Kaynakları ve Dönüştürücü Gücü",
   "type": "post"
  }
 ],
 "menu": true,
 "settings": true,
 "theme": true,
 "plugins": [
  "wpvivid-backuprestore",
  "contact-form-7"
 ],
 "themes": [
  "kadence"
 ],
 "trash": [
  1
 ],
 "launch": "auto",
 "reason": "Owner approved the Nova mockups in chat; one plan for the whole fresh-site build."
}
```

## 2. Images (media_upload: url + alt; keep key → id + url)
| key | url | alt | used on |
|---|---|---|---|
| logo | https://fatihbora.net/botox/wp-content/uploads/2025/06/Logo-1.png | Nova logosu | Header logo + footer |
| hero | https://fatihbora.net/botox/wp-content/uploads/2025/06/Banner1.png | Boynuna dokunan, bakımlı cildiyle poz veren kadın | Anasayfa hero |
| t1 | https://fatihbora.net/botox/wp-content/uploads/2025/09/Banner16.png | Cihaz destekli cilt uygulaması | Anasayfa, Tedaviler, Galeri |
| t2 | https://fatihbora.net/botox/wp-content/uploads/2025/09/Banner15.png | Yüze bakım maskesi uygulanıyor | Anasayfa, Tedaviler, Galeri |
| t3 | https://fatihbora.net/botox/wp-content/uploads/2025/09/BG11-2048x1366.jpg | Parmak ucunda bakım kremi | Anasayfa, Galeri |
| i1 | https://fatihbora.net/botox/wp-content/uploads/2025/08/3.png | (decorative, empty alt) | Cilt bakımı ikonu |
| i2 | https://fatihbora.net/botox/wp-content/uploads/2025/08/2.png | (decorative, empty alt) | Enjeksiyon ikonu |
| i3 | https://fatihbora.net/botox/wp-content/uploads/2025/08/1.png | (decorative, empty alt) | Uzman ikonu |
| g1 | https://fatihbora.net/botox/wp-content/uploads/2025/09/BG13.png | Belirgin yüz hatlarına sahip kadın portresi | Anasayfa kontür, Galeri |
| g2 | https://fatihbora.net/botox/wp-content/uploads/2025/09/2-1024x684.png | Cilt bakımı öncesi ve sonrası | Galeri |
| g3 | https://fatihbora.net/botox/wp-content/uploads/2025/09/10-1024x684.png | Erkek danışan, cilt bakımı | Galeri |
| g4 | https://fatihbora.net/botox/wp-content/uploads/2025/09/BG10.png | Ellerini boynuna koymuş gülümseyen genç kadın | Tedaviler, Galeri |
| g5 | https://fatihbora.net/botox/wp-content/uploads/2025/09/Banner14-683x1024.png | Yüzüne yaprak dokunduran kadın | Hakkımızda, Galeri |
| ba1 | https://fatihbora.net/botox/wp-content/uploads/2025/09/1-1024x684.png | Dudak dolgusu öncesi ve sonrası | Anasayfa, Galeri |
| ba2 | https://fatihbora.net/botox/wp-content/uploads/2025/09/3-1024x684.png | Göz çevresi öncesi ve sonrası | Anasayfa, Galeri |
| ba3 | https://fatihbora.net/botox/wp-content/uploads/2025/09/9-1024x683.png | Anti-aging bakım öncesi ve sonrası | Anasayfa, Galeri |
| ba4 | https://fatihbora.net/botox/wp-content/uploads/2025/09/Banner11-1-1024x684.png | Cilt yenileme öncesi ve sonrası | Galeri |
| ba5 | https://fatihbora.net/botox/wp-content/uploads/2025/09/Banner8-1024x684.png | Dudak dolgunlaştırma öncesi ve sonrası | Galeri |
| b1 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Yassiz-Bir-Cildin-Sirlarini-Kesfetmek-Dermatoloji-Uzmanlarindan-Ipuclari-1024x684.png | Ahşap kaşıkta doğal bakım yağı | Blog 1 öne çıkan görsel |
| b2 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Akneye-Elveda-Temiz-ve-Saglikli-Bir-Cilt-Icin-Dermatoloji-Cozumleri-1024x684.png | Havluyla yüzünü kurulayan genç kadın | Blog 2 öne çıkan görsel |
| b3 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Cilt-Bakimi-Icerikleri-Etkili-Urunlerin-Ardindaki-Sihri-Kesfetmek-1024x576.png | Serum, krem ve doğal bakım ürünleri | Blog 3 öne çıkan görsel |
| b4 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Guclu-ve-Isiltili-Saclar-Icin-Uzman-Ipuclari-1024x684.png | Uzun, parlak saçlarıyla gülümseyen kadın | Blog 4 öne çıkan görsel |
| b5 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Dermal-Dolgularin-Cok-Yonlulugunu-Kesfetmek-1024x683.png | Erkek danışana dolgu uygulaması | Blog 5 öne çıkan görsel, Galeri |
| b6 | https://fatihbora.net/botox/wp-content/uploads/2025/08/Sac-Kaynaklari-ve-Donusturucu-Gucu-1024x684.png | Sarı saçlarını tarayan kadın | Blog 6 öne çıkan görsel |

## 3. Theme, header, footer, settings
```json
{
 "theme": "kadence (WordPress.org)",
 "note": "Read the current shapes with theme_settings_get first (keys: kadence_global_palette, base_font, heading_font, header_*, footer_*) and keep Kadence's exact structure; only change the values below.",
 "kadence_global_palette": {
  "palette1": "#B0583F",
  "palette2": "#8E4330",
  "palette3": "#2A2024",
  "palette4": "#3D3034",
  "palette5": "#5A4B4F",
  "palette6": "#7A6A6E",
  "palette7": "#EAD8CF",
  "palette8": "#F3E6E0",
  "palette9": "#FAF7F4"
 },
 "fonts": {
  "heading_font": "Fraunces (Google), weight 400/500",
  "base_font": "Manrope (Google), weight 400, size 17px, line-height 1.6",
  "buttons": "Manrope 700"
 },
 "pages": {
  "page_title": false,
  "page_layout": "fullwidth",
  "page_content_style": "unboxed",
  "page_vertical_padding": "hide",
  "note": "Hide the default page title on pages (each page has its own H1) and let blocks run full width."
 },
 "posts": "Single posts: keep Kadence defaults with the featured image ABOVE the title (post_feature_position \"above\"), normal content width.",
 "logo": "custom_logo = the media id of image \"logo\" (Logo-1.png); logo width about 150px desktop, 120px mobile.",
 "header": {
  "top_row": "Background #2A2024, text #EAD8CF, 13px. Left: HTML \"İstanbul · Estetik, kozmetoloji ve dermatoloji kliniği\". Right: HTML \"0800 365 25 68 · info@email.com\" + social icons Instagram, Facebook.",
  "main_row": "Background #FAF7F4, bottom border 1px #EAD8CF. Left: logo. Center: primary navigation (Manrope 600, 15px). Right: header button.",
  "header_button_label": "Randevu Al",
  "header_button_link": "https://fatihbora.net/test/iletisim/",
  "mobile": "Logo left, hamburger right (mobile navigation = primary menu)."
 },
 "footer": {
  "layout": "Middle row with 4 columns = widget areas footer1..footer4 (blocks in footer-widgets.json), background #2A2024. Bottom row: HTML \"© 2026 Nova Güzellik. Tüm hakları saklıdır.\" left and \"Uygulamalar hekim muayenesi sonrası planlanır.\" right, text #BFAEA8, top border 1px #4A3B40."
 },
 "site_settings": {
  "title": "Nova Güzellik",
  "tagline": "Estetik, kozmetoloji ve dermatoloji kliniği",
  "front_page": "the page \"Anasayfa\""
 },
 "custom_css": "custom.css (whole file, mode replace)"
}
```

## 4. Site CSS (custom_css_set, mode "replace")
```css
/* Nova Güzellik — site styles (Appearance → Customize → Additional CSS, via custom_css_set) */
:root{--nv-ink:#2A2024;--nv-ink2:#5A4B4F;--nv-clay:#B0583F;--nv-clay-d:#8E4330;--nv-blush:#F3E6E0;--nv-sand:#EAD8CF;--nv-ground:#FAF7F4;--nv-white:#FFFFFF;--nv-serif:"Fraunces",Georgia,serif}
body{background:var(--nv-ground);color:var(--nv-ink)}
.entry-content>.alignfull,.entry-content>.wp-block-group.alignfull{margin-top:0;margin-bottom:0}
.nv-wrap{max-width:1240px;margin-left:auto !important;margin-right:auto !important;padding:88px 24px}
.nv-eyebrow{font-size:13px !important;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--nv-clay-d) !important;margin:0 0 12px !important}
.nv-dark .nv-eyebrow{color:#E2A48F !important}
.nv-h1{font-family:var(--nv-serif);font-weight:400;font-size:clamp(42px,5.2vw,74px) !important;line-height:1.03;letter-spacing:-.02em;text-wrap:balance;margin:0 0 22px}
.nv-h1 em,.nv-h2 em{font-style:italic;color:var(--nv-clay)}
.nv-h2{font-family:var(--nv-serif);font-weight:400;font-size:clamp(32px,3.4vw,44px) !important;line-height:1.1;text-wrap:balance;margin:0 0 18px}
.nv-h3{font-family:var(--nv-serif);font-weight:500;font-size:24px !important;line-height:1.3;margin:0}
.nv-lead{font-size:19px;line-height:1.65;color:var(--nv-ink2);max-width:52ch}
.nv-muted{color:var(--nv-ink2);line-height:1.7}
.nv-blush{background:var(--nv-blush)}
.nv-white{background:var(--nv-white);border-top:1px solid var(--nv-sand);border-bottom:1px solid var(--nv-sand)}
.nv-dark{background:var(--nv-ink);color:#FAF7F4}
.nv-dark p{color:#D8C6BF}
.nv-dark h2,.nv-dark h3,.nv-dark strong{color:#FAF7F4}
/* buttons */
.nv-btn .wp-block-button__link{background:var(--nv-clay) !important;color:#fff !important;border-radius:999px !important;padding:16px 28px !important;font-weight:700}
.nv-btn .wp-block-button__link:hover{background:var(--nv-clay-d) !important}
.nv-btn-line .wp-block-button__link{background:transparent !important;color:var(--nv-ink) !important;border:1.5px solid var(--nv-ink) !important;border-radius:999px !important;padding:15px 27px !important;font-weight:700}
.nv-link a,.nv-link{font-weight:700;color:var(--nv-clay);text-decoration:none}
/* hero */
.nv-hero{align-items:center !important;gap:56px}
.nv-checks{list-style:none;padding:18px 0 0 !important;margin:10px 0 0;border-top:1px solid var(--nv-sand);display:flex;flex-wrap:wrap;gap:10px 28px;font-size:15px;color:var(--nv-ink2)}
.nv-checks li{padding-left:26px;position:relative}
.nv-checks li:before{content:"";position:absolute;left:0;top:4px;width:14px;height:8px;border-left:2px solid var(--nv-clay);border-bottom:2px solid var(--nv-clay);transform:rotate(-45deg)}
.nv-arch{position:relative}
.nv-arch img{display:block;width:100%;height:auto}
.nv-arch-top{border-radius:240px 240px 28px 28px;overflow:hidden;background:var(--nv-blush);max-width:470px;margin:0 auto}
.nv-arch-bottom{border-radius:28px 28px 220px 220px;overflow:hidden;background:var(--nv-sand);max-width:420px;margin:0 auto}
.nv-arch-bottom img{aspect-ratio:3/4;object-fit:cover}
.nv-badge{background:#fff;border-radius:18px;padding:16px 20px !important;box-shadow:0 24px 50px -24px rgba(42,32,36,.45);max-width:240px;margin-top:-90px !important;position:relative;z-index:2}
.nv-badge .nv-big{font-family:var(--nv-serif);font-size:34px;color:var(--nv-clay);line-height:1;margin:0}
.nv-badge p{margin:4px 0 0;font-weight:600;font-size:14px}
/* cards */
.nv-cards{gap:24px !important}
.nv-card{background:var(--nv-ground);border-radius:24px;overflow:hidden;transition:transform .25s,box-shadow .25s;height:100%}
.nv-white .nv-card{background:var(--nv-ground)}
.nv-card:hover{transform:translateY(-4px);box-shadow:0 24px 48px -28px rgba(42,32,36,.45)}
.nv-card>.wp-block-image img{width:100%;height:210px;object-fit:cover;display:block}
.nv-card>.wp-block-image{margin:0}
.nv-card-body{padding:26px !important}
.nv-icon img{width:52px !important;height:52px;background:#fff;border-radius:16px;padding:6px;box-shadow:0 8px 20px -10px rgba(42,32,36,.4)}
.nv-card .nv-icon{margin:-60px 0 12px !important}
.nv-tile{background:#fff;border:1px solid var(--nv-sand);border-radius:24px;padding:30px !important}
.nv-pills{list-style:none;padding:0 !important;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.nv-pills li{background:#fff;border:1px solid var(--nv-sand);border-radius:14px;padding:14px 18px;font-weight:600}
.nv-rows{list-style:none;padding:0 !important;margin:0}
.nv-rows li{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--nv-sand)}
.nv-rows li:after{content:"→";color:var(--nv-clay-d)}
.nv-rule>.wp-block-column{border-top:2px solid var(--nv-ink);padding-top:22px}
.nv-dark .nv-rule>.wp-block-column{border-top-color:#5A4B4F}
.nv-num{font-family:var(--nv-serif);font-size:34px !important;color:#E2A48F;margin:0 0 6px !important}
/* before/after + gallery */
.nv-ba figure,.wp-block-image.nv-ba{background:#fff;border-radius:22px;overflow:hidden;margin:0}
.nv-ba img{aspect-ratio:3/2;object-fit:cover;width:100%}
.nv-ba figcaption{padding:14px 20px;margin:0 !important;font-weight:600;text-align:left;color:var(--nv-ink)}
.nv-gallery img{aspect-ratio:3/4;object-fit:cover;border-radius:18px;width:100%}
.nv-gallery .wp-block-image{margin:0}
/* faq */
.nv-faq .wp-block-details{border-bottom:1px solid var(--nv-sand);padding:20px 0}
.nv-faq summary{font-size:18px;font-weight:600;cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:16px}
.nv-faq summary::-webkit-details-marker{display:none}
.nv-faq summary:after{content:"+";color:var(--nv-clay);font-size:24px;line-height:1}
.nv-faq details[open] summary:after{content:"−"}
.nv-faq details p{color:var(--nv-ink2);line-height:1.7;margin:14px 0 0}
/* prices */
.nv-price{background:#fff;border:1px solid var(--nv-sand);border-radius:28px;padding:36px !important}
.nv-price ul{list-style:none;padding:0 !important;margin:0}
.nv-price li{display:flex;gap:12px;align-items:baseline;padding:14px 0;border-bottom:1px dashed #DCC6BC}
.nv-price li strong{margin-left:auto;font-variant-numeric:tabular-nums;white-space:nowrap}
/* blog */
.nv-posts .wp-block-post-featured-image img{aspect-ratio:16/10;object-fit:cover;border-radius:18px}
.nv-posts .wp-block-post-title{font-family:var(--nv-serif);font-weight:500;font-size:22px;line-height:1.3}
.nv-posts .wp-block-post-title a{color:var(--nv-ink);text-decoration:none}
.nv-posts .wp-block-post-terms a{font-size:13px;font-weight:700;text-transform:uppercase;color:var(--nv-clay-d);text-decoration:none}
.nv-chips .wp-block-button__link{background:transparent !important;color:var(--nv-ink) !important;border:1px solid #DCC6BC;border-radius:999px !important;padding:9px 16px !important;font-size:14px;font-weight:600}
/* contact */
.nv-info p{background:#fff;border:1px solid var(--nv-sand);border-radius:18px;padding:16px 22px;margin:0 0 12px;font-size:18px;font-weight:600}
.nv-info p strong{display:block;font-size:13px;color:var(--nv-clay-d);letter-spacing:.06em}
.nv-form{background:#fff;border:1px solid var(--nv-sand);border-radius:28px;padding:40px !important}
.nv-form .wpcf7 input:not([type=checkbox]):not([type=submit]),.nv-form .wpcf7 select,.nv-form .wpcf7 textarea{width:100%;background:var(--nv-ground);border:1px solid #DCC6BC;border-radius:14px;padding:14px 16px;font-size:16px}
.nv-form .wpcf7 input[type=submit]{background:var(--nv-clay);color:#fff;border:0;border-radius:999px;padding:16px 32px;font-weight:700;cursor:pointer;width:100%}
.nv-map{background:var(--nv-sand);border-radius:28px;min-height:320px;display:flex;align-items:center;justify-content:center}
/* cta + quote */
.nv-cta{background:var(--nv-ink);border-radius:32px;padding:64px 56px !important;align-items:center !important}
.nv-cta h2{color:#FAF7F4}
.nv-cta p{color:#EAD8CF}
.nv-quote img{border-radius:50%;width:120px;height:120px;object-fit:cover}
/* header & footer (Kadence) */
.site-header .header-button{background:var(--nv-clay) !important;border-radius:999px !important}
.site-footer{background:var(--nv-ink)}
.site-footer,.site-footer a{color:#EAD8CF}
.site-footer a:hover{color:#fff}
.site-footer .widget-title{color:#fff;font-size:13px;letter-spacing:.12em;text-transform:uppercase}
.nv-flogo{background:#FAF7F4;border-radius:12px;padding:10px 14px;display:inline-block}
.nv-flinks{list-style:none;padding:0 !important}
.nv-flinks li{margin:0 0 8px}
@media (max-width:781px){.nv-wrap{padding:64px 20px}.nv-cta{padding:40px 28px !important}.nv-badge{margin-top:-60px !important}}
.nv-round img{border-radius:28px;aspect-ratio:4/3;object-fit:cover;width:100%}
.nv-rows-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));column-gap:28px}
.nv-pt0{padding-top:0 !important}
.nv-narrow{max-width:880px}
.nv-center{text-align:center}
.nv-mission{font-family:var(--nv-serif);font-size:clamp(26px,3vw,38px) !important;line-height:1.35;color:#FAF7F4 !important;max-width:30ch;margin:0 auto;text-wrap:balance}
.nv-grid{gap:20px}
.nv-gal img{aspect-ratio:3/4;object-fit:cover;border-radius:18px;width:100%}
.nv-tile.nv-blush{background:var(--nv-blush);border-color:transparent}
.nv-tile .nv-big{font-family:var(--nv-serif);font-size:44px;color:var(--nv-clay);line-height:1;margin:0}
.nv-dark .wp-block-heading{color:#FAF7F4}
```

## 5. Contact form (form_create)
```json
{
 "title": "Nova randevu formu",
 "submit": "Gönder",
 "subject": "Yeni randevu talebi — Nova",
 "fields": [
  {
   "name": "ad-soyad",
   "label": "Adın soyadın",
   "type": "text",
   "required": true
  },
  {
   "name": "telefon",
   "label": "Telefon",
   "type": "tel",
   "required": true
  },
  {
   "name": "e-posta",
   "label": "E-posta",
   "type": "email",
   "required": true
  },
  {
   "name": "uygulama",
   "label": "İlgilendiğin uygulama",
   "type": "select",
   "options": [
    "Cilt bakımı & dermatoloji",
    "Botoks & dolgu",
    "Cihaz destekli kozmetoloji",
    "Saç bakımı",
    "Vücut şekillendirme",
    "Emin değilim"
   ]
  },
  {
   "name": "mesaj",
   "label": "Mesajın",
   "type": "textarea"
  },
  {
   "name": "kvkk",
   "label": "KVKK aydınlatma metnini okudum, bilgilerimin randevu için kullanılmasını onaylıyorum.",
   "type": "acceptance",
   "required": true
  }
 ],
 "recipient": "(leave empty = site admin email)"
}
```

## 6. Footer widgets (widgets_set, one call per area)
```json
{
 "footer1": [
  "<!-- wp:image {\"width\":\"140px\",\"sizeSlug\":\"full\",\"linkDestination\":\"custom\",\"className\":\"nv-flogo\"} -->\n<figure class=\"wp-block-image size-full is-resized nv-flogo\"><a href=\"https://fatihbora.net/test/\"><img src=\"{{img:logo}}\" alt=\"Nova\" style=\"width:140px\"/></a></figure>\n<!-- /wp:image -->",
  "<!-- wp:paragraph -->\n<p>Bilimin estetikle buluştuğu klinik. Kişiye özel cilt bakımı, enjeksiyon ve dermatoloji uygulamaları.</p>\n<!-- /wp:paragraph -->"
 ],
 "footer2": [
  "<!-- wp:heading {\"level\":2,\"className\":\"widget-title\"} -->\n<h2 class=\"wp-block-heading widget-title\">Tedaviler</h2>\n<!-- /wp:heading -->",
  "<!-- wp:list {\"className\":\"nv-flinks\"} -->\n<ul class=\"wp-block-list nv-flinks\"><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/tedaviler/\">Cilt bakımı &amp; dermatoloji</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/tedaviler/\">Enjeksiyon uygulamaları</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/tedaviler/\">Cihaz destekli kozmetoloji</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/fiyatlar/\">Fiyatlar</a></li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->"
 ],
 "footer3": [
  "<!-- wp:heading {\"level\":2,\"className\":\"widget-title\"} -->\n<h2 class=\"wp-block-heading widget-title\">Klinik</h2>\n<!-- /wp:heading -->",
  "<!-- wp:list {\"className\":\"nv-flinks\"} -->\n<ul class=\"wp-block-list nv-flinks\"><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/hakkimizda/\">Hakkımızda</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/oncesi-ve-sonrasi/\">Öncesi &amp; Sonrası</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/sik-sorulan-sorular/\">Sık sorulan sorular</a></li>\n<!-- /wp:list-item --><!-- wp:list-item -->\n<li><a href=\"https://fatihbora.net/test/blog/\">Blog</a></li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->"
 ],
 "footer4": [
  "<!-- wp:heading {\"level\":2,\"className\":\"widget-title\"} -->\n<h2 class=\"wp-block-heading widget-title\">İletişim</h2>\n<!-- /wp:heading -->",
  "<!-- wp:paragraph -->\n<p>0800 365 25 68<br>info@email.com<br>İstanbul, Türkiye 34000</p>\n<!-- /wp:paragraph -->",
  "<!-- wp:paragraph -->\n<p><a href=\"https://instagram.com\">Instagram</a> · <a href=\"https://facebook.com\">Facebook</a> · <a href=\"https://x.com\">X</a></p>\n<!-- /wp:paragraph -->"
 ]
}
```

## 7. Menu (menu_set)
```json
{
 "name": "Ana menü",
 "location": "primary (also set it as the mobile menu)",
 "items": [
  "Anasayfa",
  "Tedaviler",
  "Fiyatlar",
  "Öncesi ve Sonrası (menu label: \"Öncesi & Sonrası\")",
  "Hakkımızda",
  "Blog",
  "İletişim"
 ],
 "note": "Items point to the page ids you created. Sık Sorulan Sorular is linked from the homepage and footer, not the main menu."
}
```

## 8. Pages (content_create_draft, post_type page, then content_publish)

### Page: Anasayfa
```html
<!-- wp:group {"align":"full","className":"nv-hero-band","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull nv-hero-band"><!-- wp:columns {"className":"nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Estetik · Kozmetoloji · Dermatoloji</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Gerçek güzelliğini <em>ortaya çıkar.</em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-lead"} -->
<p class="nv-lead">Kendine olan güvenini yeniden kazan. Uzman ekibimizle, sana özel hazırlanan bakım ve estetik uygulamalarla zamansız güzelliğini kucakla.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Hemen Randevu Al</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"nv-btn-line"} -->
<div class="wp-block-button nv-btn-line"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/tedaviler/">Tedavileri İncele</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:list {"className":"nv-checks"} -->
<ul class="wp-block-list nv-checks"><!-- wp:list-item -->
<li>Uzman hekim kadrosu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Kişiye özel tedavi planı</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Güvenli, onaylı ürünler</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"nv-arch nv-arch-top"} -->
<figure class="wp-block-image size-full nv-arch nv-arch-top"><img src="{{img:hero}}" alt="Boynuna dokunan, bakımlı cildiyle poz veren kadın"/></figure>
<!-- /wp:image -->

<!-- wp:group {"className":"nv-badge","layout":{"type":"default"}} -->
<div class="wp-block-group nv-badge"><!-- wp:paragraph {"className":"nv-big"} -->
<p class="nv-big">%20</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>İlk kozmetoloji tedavinde yeni danışan indirimi</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nv-white","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull nv-white"><!-- wp:group {"className":"nv-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Hizmetlerimiz</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Son teknolojiyle, sana özel güzellik</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"nv-cards"} -->
<div class="wp-block-columns nv-cards"><!-- wp:column {"className":"nv-card"} -->
<div class="wp-block-column nv-card"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{{img:t2}}" alt="Yüze bakım maskesi uygulanıyor"/></figure>
<!-- /wp:image -->

<!-- wp:group {"className":"nv-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group nv-card-body"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i1}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"className":"nv-h3"} -->
<h3 class="wp-block-heading nv-h3">Dermatoloji &amp; Cilt Bakımı</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Peeling, akne tedavisi, LED terapi ve nem bakımlarıyla cildinde fark yarat.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/tedaviler/">İncele →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"nv-card"} -->
<div class="wp-block-column nv-card"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{{img:t1}}" alt="Cihaz destekli cilt uygulaması"/></figure>
<!-- /wp:image -->

<!-- wp:group {"className":"nv-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group nv-card-body"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i3}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"className":"nv-h3"} -->
<h3 class="wp-block-heading nv-h3">Cihaz Destekli Kozmetoloji</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Lazer, mikrodermabrazyon ve cilt sıkılaştırma cihazlarıyla rutinini bir üst seviyeye taşı.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/tedaviler/">İncele →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"nv-card"} -->
<div class="wp-block-column nv-card"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="{{img:t3}}" alt="Parmak ucunda bakım kremi"/></figure>
<!-- /wp:image -->

<!-- wp:group {"className":"nv-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group nv-card-body"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i2}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"className":"nv-h3"} -->
<h3 class="wp-block-heading nv-h3">Enjeksiyon Uygulamaları</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Botoks ve dermal dolgularla doğal, dengeli ve tazelenmiş bir görünüm.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/tedaviler/">İncele →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"className":"nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"nv-arch nv-arch-bottom"} -->
<figure class="wp-block-image size-full nv-arch nv-arch-bottom"><img src="{{img:g1}}" alt="Belirgin yüz hatlarına sahip kadın portresi"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Kontür estetiği</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Ameliyatsız kontür teknikleriyle yüz hatlarını dengele</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Yüzünün doğal oranlarını koruyarak hacim kaybını gideriyor, hatları belirginleştiriyoruz. Her uygulama, muayene sonrası sana özel planlanır.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"nv-pills"} -->
<ul class="wp-block-list nv-pills"><!-- wp:list-item -->
<li>Yanak dolgusu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Çene hattı kontürü</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Dudak dolgusu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ameliyatsız burun estetiği</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Şakak dolgusu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Göz altı dolgusu</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/fiyatlar/">Enjeksiyon fiyatlarını gör →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align":"full","className":"nv-blush","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className":"nv-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Öncesi &amp; Sonrası</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Dönüşümleri kendin gör</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"nv-cards"} -->
<div class="wp-block-columns nv-cards"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba1}}" alt="Dudak dolgusu öncesi ve sonrası"/><figcaption class="wp-element-caption">Dudak dolgusu</figcaption></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba2}}" alt="Göz çevresi uygulaması öncesi ve sonrası"/><figcaption class="wp-element-caption">Göz çevresi</figcaption></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba3}}" alt="Anti-aging bakım öncesi ve sonrası"/><figcaption class="wp-element-caption">Anti-aging bakım</figcaption></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/oncesi-ve-sonrasi/">Tüm galeri →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"nv-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Neden Nova?</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Güzelliği, her seferinde bir yüzle şekillendiriyoruz</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"nv-rule"} -->
<div class="wp-block-columns nv-rule"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Profesyonellik</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Kliniğimiz, kozmetoloji ve dermatoloji alanında eğitimli, deneyimli uzmanlardan oluşur.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Geniş hizmet yelpazesi</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Cilt bakımından enjeksiyona, saç bakımından vücut şekillendirmeye kadar tek çatı altında.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Kişiselleştirilmiş yaklaşım</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Her plan senin cildine, ihtiyacına ve beklentine göre muayene sonrası birlikte hazırlanır.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"nv-white","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull nv-white"><!-- wp:columns {"className":"nv-wrap"} -->
<div class="wp-block-columns nv-wrap"><!-- wp:column {"width":"38%"} -->
<div class="wp-block-column" style="flex-basis:38%"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Sık sorulan sorular</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Aklındaki sorular</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"nv-muted"} -->
<p class="nv-muted">Cevabını bulamadığın bir şey mi var? Bize yaz, en kısa sürede dönelim.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/sik-sorulan-sorular/">Tüm sorular →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"62%","className":"nv-faq"} -->
<div class="wp-block-column nv-faq" style="flex-basis:62%"><!-- wp:details -->
<details class="wp-block-details"><summary>Randevuyu nasıl alabilirim?</summary><!-- wp:paragraph -->
<p>İletişim sayfasındaki formu doldurabilir, bizi arayabilir ya da Instagram üzerinden yazabilirsin. Ekibimiz sana uygun gün ve saati belirlemek için kısa sürede dönüş yapar.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Kullanılan ürünler güvenli mi?</summary><!-- wp:paragraph -->
<p>Uygulamalarımızda yalnızca Sağlık Bakanlığı onaylı, orijinal ürünler kullanılır. Her işlem öncesi muayene yapılır ve sana uygun olup olmadığı birlikte değerlendirilir.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Tedavimin sonuçları ne kadar sürer?</summary><!-- wp:paragraph -->
<p>Süre uygulamaya ve kişiye göre değişir. Botoks etkisi genellikle birkaç ay, dolgular daha uzun sürer. Muayenede sana beklenen süreyi net olarak anlatırız.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Hangi hizmetleri sunuyorsunuz?</summary><!-- wp:paragraph -->
<p>Cilt bakımı ve dermatoloji, cihaz destekli kozmetoloji, botoks ve dolgu uygulamaları, saç bakımı ve vücut şekillendirme hizmetleri sunuyoruz.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Uygulama sırasında ne beklemeliyim?</summary><!-- wp:paragraph -->
<p>Önce cildini ve beklentilerini konuşuruz. Uygulama çoğu zaman kısa sürer; sonrasında dikkat etmen gerekenleri yazılı olarak da iletiriz.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"nv-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className":"nv-eyebrow"} -->
<p class="nv-eyebrow">Blog</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Uzmanlarımızdan ipuçları</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":1,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"nv-posts"} -->
<div class="wp-block-query nv-posts"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true} /-->

<!-- wp:post-terms {"term":"category"} /-->

<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:paragraph {"className":"nv-link"} -->
<p class="nv-link"><a href="https://fatihbora.net/test/blog/">Tüm yazılar →</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"nv-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:group {"className":"nv-cta","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group nv-cta"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"className":"nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Cildini uzmanlara emanet et</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ücretsiz ön görüşmede cildini değerlendirelim, sana uygun planı birlikte hazırlayalım.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Randevu Yap</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

### Page: Tedaviler
```html
<!-- wp:group {"align": "full", "className": "nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Tedaviler</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Cildine ve hatlarına özel uygulamalar</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">Her tedavi ücretsiz ön görüşmeyle başlar. Uzmanımız cildini değerlendirir, sana uygun yöntemi ve seans sayısını birlikte belirleriz.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"className": "nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-round"} -->
<figure class="wp-block-image size-large nv-round"><img src="{{img:t2}}" alt="Yüze bakım maskesi uygulanıyor"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i1}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Dermatoloji &amp; Cilt Bakımı</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Işıltını ortaya çıkar. Cildinin ihtiyacına göre temizlik, yenileme ve nem dengesi sağlayan bakımlar.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className": "nv-rows"} -->
<ul class="wp-block-list nv-rows"><!-- wp:list-item -->
<li>Yüz temizliği ve nemlendirme</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Kimyasal peeling</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Akne tedavisi</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>LED ışık terapisi</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Hyalüronik asit uygulaması</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Oksijen bakımı</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"className": "nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i3}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Cihaz Destekli Kozmetoloji</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Gelişmiş lazer sistemleri ve vücut şekillendirme cihazlarıyla, cilt bakımında en güncel teknolojiyi güvenle deneyimle.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className": "nv-rows"} -->
<ul class="wp-block-list nv-rows"><!-- wp:list-item -->
<li>Mikrodermabrazyon</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Cilt sıkılaştırma</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Anti-aging bakım</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Vücut şekillendirme</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-round"} -->
<figure class="wp-block-image size-large nv-round"><img src="{{img:t1}}" alt="Cihaz destekli cilt uygulaması"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"className": "nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-round"} -->
<figure class="wp-block-image size-large nv-round"><img src="{{img:g4}}" alt="Ellerini boynuna koymuş gülümseyen genç kadın"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i2}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Botoks &amp; Enjeksiyon</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Güzelliği yeniden tanımla. Doğal ifadeni koruyarak çizgileri yumuşatan ve hacim kazandıran uygulamalar.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className": "nv-rows nv-rows-2"} -->
<ul class="wp-block-list nv-rows nv-rows-2"><!-- wp:list-item -->
<li>Botoks</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Dermal dolgu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Dudak dolgunlaştırma</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Çene hattı şekillendirme</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Yanak dolgusu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ameliyatsız burun</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Göz altı dolgusu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Şakak dolgusu</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align": "full", "className": "nv-dark", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-dark"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Süreç</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Nova’da bir tedavi nasıl ilerler?</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className": "nv-rule"} -->
<div class="wp-block-columns nv-rule"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-num"} -->
<p class="nv-num">1</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Ön görüşme</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cildini ve beklentilerini dinler, muayene ederiz.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-num"} -->
<p class="nv-num">2</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Kişiye özel plan</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Yöntem, seans sayısı ve ücret netleşir.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-num"} -->
<p class="nv-num">3</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Uygulama</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Konforlu ortamda, onaylı ürünlerle uygulanır.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-num"} -->
<p class="nv-num">4</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Takip</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Sonuçları birlikte değerlendirir, bakım önerileri veririz.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Ücretsiz ön görüşme al</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

### Page: Fiyatlar
```html
<!-- wp:group {"align": "full", "className": "nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Fiyatlar</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Güncel fiyat listesi</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">Fiyatlar bilgilendirme amaçlıdır. Kesin ücret, muayene sonrası sana özel plana göre belirlenir.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"className": "nv-wrap"} -->
<div class="wp-block-columns nv-wrap"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-price", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-price"><!-- wp:heading {"className": "nv-h3"} -->
<h2 class="wp-block-heading nv-h3">Cilt Bakımı Uygulamaları</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Yüz temizliği ve nemlendirme <strong>2.400 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Kimyasal peeling <strong>3.600 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Mikrodermabrazyon <strong>3.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Anti-aging bakım <strong>4.050 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Akne tedavisi <strong>2.850 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>LED ışık terapisi <strong>3.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Hyalüronik asit uygulaması <strong>3.600 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Cilt sıkılaştırma <strong>4.500 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Oksijen bakımı <strong>3.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Kişiye özel cilt bakımı danışmanlığı <strong>1.500 TL</strong></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-price", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-price"><!-- wp:heading {"className": "nv-h3"} -->
<h2 class="wp-block-heading nv-h3">Enjeksiyon Uygulamaları</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Botoks (ünite başına) <strong>360 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Dermal dolgu (enjektör başına) <strong>15.000 – 24.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Dudak dolgunlaştırma <strong>18.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Çene hattı şekillendirme <strong>21.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Yanak dolgusu <strong>18.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ameliyatsız burun şekillendirme <strong>21.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Göz altı dolgusu <strong>18.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Marionette çizgisi düzeltme <strong>15.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Çene dolgunlaştırma <strong>21.000 TL</strong></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Şakak dolgusu <strong>18.000 TL</strong></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"className": "nv-wrap nv-pt0"} -->
<div class="wp-block-columns nv-wrap nv-pt0"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-cta", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-cta"><!-- wp:heading {"className": "nv-h3"} -->
<h2 class="wp-block-heading nv-h3">Saç bakımı &amp; vücut şekillendirme</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Bu uygulamaların ücreti seans sayısına ve bölgeye göre değişir. Ön görüşmede sana net fiyat verelim.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Fiyat bilgisi al</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-tile nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-tile nv-blush"><!-- wp:paragraph {"className": "nv-big"} -->
<p class="nv-big">%20</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Yeni danışan fırsatı</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">İlk kozmetoloji tedavinde %20 indirimden yararlan.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
```

### Page: Öncesi ve Sonrası
```html
<!-- wp:group {"align": "full", "className": "nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Galeri</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Dönüşümler: öncesi ve sonrası</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">Sonuçlar kişiden kişiye değişir. Fotoğraflar danışanlarımızın izniyle paylaşılmaktadır.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:group {"className": "nv-grid", "layout": {"type": "grid", "minimumColumnWidth": "340px"}} -->
<div class="wp-block-group nv-grid"><!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba1}}" alt="Dudak dolgusu öncesi ve sonrası"/><figcaption class="wp-element-caption">Dudak dolgusu</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba5}}" alt="Dudak dolgunlaştırma öncesi ve sonrası"/><figcaption class="wp-element-caption">Dudak dolgunlaştırma</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba2}}" alt="Göz çevresi öncesi ve sonrası"/><figcaption class="wp-element-caption">Göz çevresi</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba3}}" alt="Anti-aging bakım öncesi ve sonrası"/><figcaption class="wp-element-caption">Anti-aging bakım</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:ba4}}" alt="Cilt yenileme öncesi ve sonrası"/><figcaption class="wp-element-caption">Cilt yenileme</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-ba"} -->
<figure class="wp-block-image size-large nv-ba"><img src="{{img:g2}}" alt="Cilt bakımı öncesi ve sonrası"/><figcaption class="wp-element-caption">Cilt bakımı</figcaption></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap nv-gallery nv-pt0", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap nv-gallery nv-pt0"><!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Klinikten kareler</h2>
<!-- /wp:heading -->

<!-- wp:group {"className": "nv-grid", "layout": {"type": "grid", "minimumColumnWidth": "240px"}} -->
<div class="wp-block-group nv-grid"><!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:g1}}" alt="Portre"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:g5}}" alt="Yüzünde yaprakla poz veren kadın"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:t1}}" alt="Cihaz destekli uygulama"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:g3}}" alt="Erkek danışan, cilt bakımı"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:t2}}" alt="Bakım maskesi"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:g4}}" alt="Gülümseyen genç kadın"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:t3}}" alt="Bakım kremi"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug": "large", "linkDestination": "none", "className": "nv-gal"} -->
<figure class="wp-block-image size-large nv-gal"><img src="{{img:b5}}" alt="Erkek danışana dolgu uygulaması"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:group {"className": "nv-cta", "layout": {"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"}} -->
<div class="wp-block-group nv-cta"><!-- wp:group {"layout": {"type": "default"}} -->
<div class="wp-block-group"><!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Cildini uzmanlara emanet et</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ücretsiz ön görüşmede cildini değerlendirelim, sana uygun planı birlikte hazırlayalım.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Randevu Yap</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

### Page: Hakkımızda
```html
<!-- wp:columns {"className": "nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Hakkımızda</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Bilim ve güzelliğin <em>buluşma noktası</em></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">Nova, ruhunu dinlendirmek ve cildini yeniden canlandırmak için tasarlanmış özel bir bakım alanı. Modern estetik uygulamalarını, özenli bakım ritüelleriyle birleştiriyoruz.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">Kozmetoloji ve dermatoloji alanında eğitimli ekibimiz, her danışanı dinleyerek başlar; sonra cilde ve beklentiye uygun planı birlikte kurar.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"sizeSlug": "full", "linkDestination": "none", "className": "nv-arch nv-arch-top"} -->
<figure class="wp-block-image size-full nv-arch nv-arch-top"><img src="{{img:g5}}" alt="Yüzüne yaprak dokunduran kadın"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"align": "full", "className": "nv-dark", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-dark"><!-- wp:group {"className": "nv-wrap nv-center", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap nv-center"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Misyonumuz</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className": "nv-mission"} -->
<p class="nv-mission">Güzellik sektöründe kapsayıcılığı, çeşitliliği ve öz-kabulü savunarak olumlu değişimin öncüsü olmak; bireysel güzelliği her yönüyle kutlayan hizmetler sunmak.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Neye önem veriyoruz?</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className": "nv-cards"} -->
<div class="wp-block-columns nv-cards"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-tile", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-tile"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i3}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Uzmanlık</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Her uygulama, alanında eğitimli uzmanlarca ve muayene sonrası yapılır.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-tile", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-tile"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i1}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Güvenli ürünler</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Yalnızca onaylı, orijinal ürünler ve bakımlı, steril ekipman kullanırız.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-tile", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-tile"><!-- wp:image {"width":"52px","sizeSlug":"full","linkDestination":"none","className":"nv-icon"} -->
<figure class="wp-block-image size-full is-resized nv-icon"><img src="{{img:i2}}" alt="" style="width:52px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level": 3} -->
<h3 class="wp-block-heading">Doğal sonuçlar</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-muted"} -->
<p class="nv-muted">Amacımız seni değiştirmek değil; en iyi halini ortaya çıkarmak.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:group {"className": "nv-cta", "layout": {"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"}} -->
<div class="wp-block-group nv-cta"><!-- wp:group {"layout": {"type": "default"}} -->
<div class="wp-block-group"><!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Cildini uzmanlara emanet et</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ücretsiz ön görüşmede cildini değerlendirelim, sana uygun planı birlikte hazırlayalım.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Randevu Yap</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

### Page: Sık Sorulan Sorular
```html
<!-- wp:group {"align": "full", "className": "nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Sık sorulan sorular</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Merak ettiklerin</h1>
<!-- /wp:heading --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap nv-narrow nv-faq", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap nv-narrow nv-faq"><!-- wp:details -->
<details class="wp-block-details"><summary>Randevuyu nasıl alabilirim?</summary><!-- wp:paragraph -->
<p>İletişim sayfasındaki formu doldurabilir, bizi arayabilir ya da Instagram üzerinden yazabilirsin. Ekibimiz sana uygun gün ve saati belirlemek için kısa sürede dönüş yapar.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Kullanılan ürünler güvenli mi?</summary><!-- wp:paragraph -->
<p>Uygulamalarımızda yalnızca Sağlık Bakanlığı onaylı, orijinal ürünler kullanılır. Her işlem öncesi muayene yapılır ve sana uygun olup olmadığı birlikte değerlendirilir.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Tedavimin sonuçları ne kadar sürer?</summary><!-- wp:paragraph -->
<p>Süre uygulamaya ve kişiye göre değişir. Botoks etkisi genellikle birkaç ay, dolgular daha uzun sürer. Muayenede sana beklenen süreyi net olarak anlatırız.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Hangi hizmetleri sunuyorsunuz?</summary><!-- wp:paragraph -->
<p>Cilt bakımı ve dermatoloji, cihaz destekli kozmetoloji, botoks ve dolgu uygulamaları, saç bakımı ve vücut şekillendirme hizmetleri sunuyoruz.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Uygulama sırasında ne beklemeliyim?</summary><!-- wp:paragraph -->
<p>Önce cildini ve beklentilerini konuşuruz. Uygulama çoğu zaman kısa sürer; sonrasında dikkat etmen gerekenleri yazılı olarak da iletiriz.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Kişisel güzellik tavsiyesi alabilir miyim?</summary><!-- wp:paragraph -->
<p>Evet. Ön görüşmede cilt tipine ve günlük rutinine göre ev bakımı önerileri de veriyoruz.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Uygulamadan sonra günlük hayatıma dönebilir miyim?</summary><!-- wp:paragraph -->
<p>Çoğu uygulamada aynı gün günlük hayatına dönebilirsin. Hafif kızarıklık veya şişlik olursa kısa sürede geçer; sana özel önerileri muayenede paylaşırız.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Ödeme seçenekleriniz neler?</summary><!-- wp:paragraph -->
<p>Ödeme ve taksit seçenekleri için bizi arayabilir ya da ön görüşmede sorabilirsin.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:group {"className": "nv-cta", "layout": {"type": "flex", "flexWrap": "wrap", "justifyContent": "space-between"}} -->
<div class="wp-block-group nv-cta"><!-- wp:group {"layout": {"type": "default"}} -->
<div class="wp-block-group"><!-- wp:heading {"className": "nv-h2"} -->
<h2 class="wp-block-heading nv-h2">Cildini uzmanlara emanet et</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ücretsiz ön görüşmede cildini değerlendirelim, sana uygun planı birlikte hazırlayalım.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"nv-btn"} -->
<div class="wp-block-button nv-btn"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/iletisim/">Randevu Yap</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

### Page: Blog
```html
<!-- wp:group {"align": "full", "className": "nv-blush", "layout": {"type": "default"}} -->
<div class="wp-block-group alignfull nv-blush"><!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">Blog</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Uzmanlarımızdan ipuçları</h1>
<!-- /wp:heading -->

<!-- wp:buttons {"className": "nv-chips"} -->
<div class="wp-block-buttons nv-chips"><!-- wp:button {"className":"nv-chip"} -->
<div class="wp-block-button nv-chip"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/category/dermatoloji/">Dermatoloji</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"nv-chip"} -->
<div class="wp-block-button nv-chip"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/category/guzellik-tedavileri/">Güzellik Tedavileri</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"nv-chip"} -->
<div class="wp-block-button nv-chip"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/category/cilt-bakimi/">Cilt Bakımı</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"nv-chip"} -->
<div class="wp-block-button nv-chip"><a class="wp-block-button__link wp-element-button" href="https://fatihbora.net/test/category/sac-bakimi/">Saç Bakımı</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className": "nv-wrap", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap"><!-- wp:query {"queryId":2,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"nv-posts"} -->
<div class="wp-block-query nv-posts"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true} /-->

<!-- wp:post-terms {"term":"category"} /-->

<!-- wp:post-title {"level":2,"isLink":true} /-->

<!-- wp:post-excerpt {"moreText":"Devamını oku →","excerptLength":24} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
```

### Page: İletişim
```html
<!-- wp:columns {"className": "nv-wrap nv-hero"} -->
<div class="wp-block-columns nv-wrap nv-hero"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className": "nv-eyebrow"} -->
<p class="nv-eyebrow">İletişim</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level": 1, "className": "nv-h1"} -->
<h1 class="wp-block-heading nv-h1">Randevu al, sorunu sor</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className": "nv-lead"} -->
<p class="nv-lead">İnce çizgileri yumuşatmak, dudaklarına dolgunluk katmak ya da cildini yenilemek… Formu doldur, ekibimiz seni arasın.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className": "nv-info", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-info"><!-- wp:paragraph -->
<p><strong>TELEFON</strong>0800 365 25 68</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>E-POSTA</strong>info@email.com</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>ADRES</strong>İstanbul, Türkiye 34000</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>ÇALIŞMA SAATLERİ</strong>[Pzt–Cmt 10:00–19:00]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className": "nv-form", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-form"><!-- wp:heading {"className": "nv-h3"} -->
<h2 class="wp-block-heading nv-h3">Ücretsiz ön görüşme</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
{{form_shortcode}}
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"className": "nv-wrap nv-pt0", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-wrap nv-pt0"><!-- wp:group {"className": "nv-map", "layout": {"type": "default"}} -->
<div class="wp-block-group nv-map"><!-- wp:paragraph -->
<p>[Google Haritalar — klinik konumu]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
```

## 9. Blog posts (content_create_draft post_type post + excerpt; post_settings_set category + featured image; publish)

### Post: Yaşsız Bir Cildin Sırları: Dermatoloji Uzmanlarından İpuçları
- Category: **Dermatoloji**
- Featured image: **b1**
- Excerpt: Güneş koruması, düzenli nem ve doğru aktif içeriklerle cildini yıllar boyunca nasıl koruyabileceğini anlatıyoruz.
```html
<!-- wp:paragraph -->
<p>Cildin yaşlanması tek bir nedene bağlı değildir. Genetik, güneş, uyku, beslenme ve günlük bakım alışkanlıkları birlikte çalışır. İyi haber şu: bu etkenlerin çoğu senin elinde.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Güneş koruması her gün</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Erken yaşlanmanın en büyük nedeni güneş ışınlarıdır. Kış dahil her sabah, en az SPF 30 geniş spektrumlu bir güneş koruyucu sür; dışarıdaysan iki saatte bir yenile.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Nemi koru, bariyeri güçlendir</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kuru bir cilt ince çizgileri daha belirgin gösterir. Hyalüronik asit, seramid ve gliserin içeren nemlendiriciler cilt bariyerini destekler.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Doğru aktif içerikler</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong>Retinoidler:</strong> hücre yenilenmesini destekler; akşamları, düşük dozla başla.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>C vitamini:</strong> sabahları antioksidan koruma sağlar ve ton eşitler.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Peptitler:</strong> cildin daha dolgun ve esnek görünmesine yardımcı olur.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Klinikte destek</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ev bakımı temeldir; kimyasal peeling, mikrodermabrazyon veya cilt sıkılaştırma gibi uygulamalar ise sonuçlarını bir adım öteye taşıyabilir. Hangi yöntemin sana uygun olduğunu muayenede birlikte belirleriz.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```

### Post: Akneye Elveda: Temiz ve Sağlıklı Bir Cilt İçin Çözümler
- Category: **Güzellik Tedavileri**
- Featured image: **b2**
- Excerpt: Akneyi tetikleyen etkenler, evde yapılabilecekler ve klinikte uygulanan tedavi seçenekleri.
```html
<!-- wp:paragraph -->
<p>Akne yalnızca ergenlik dönemine ait değildir; yetişkinlikte de stres, hormonlar ve yanlış ürünler nedeniyle ortaya çıkabilir. Doğru yaklaşımla kontrol altına almak mümkündür.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Akneyi ne tetikler?</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Fazla sebum üretimi ve tıkanan gözenekler</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Hormonal dalgalanmalar</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Stres ve düzensiz uyku</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Gözenekleri tıkayan (komedojenik) kozmetikler</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Evde yapabileceklerin</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cildini günde iki kez nazik bir temizleyiciyle yıka, sivilceleri sıkmaktan kaçın ve “non-komedojenik” etiketli ürünler seç. Salisilik asit veya benzoil peroksit içeren ürünler hafif aknede yardımcı olabilir.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Klinikte neler yapılabilir?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Derin cilt temizliği, kimyasal peeling ve LED ışık terapisi akneyle mücadelede sık kullanılan yöntemlerdir. Akne izleri için ise cilt yenileyici uygulamalar planlanabilir. Tedavi, cildinin durumuna göre uzman tarafından belirlenir.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```

### Post: Cilt Bakımı İçerikleri: Etkili Ürünlerin Ardındaki Bilim
- Category: **Cilt Bakımı**
- Featured image: **b3**
- Excerpt: Hyalüronik asit, retinol, C vitamini… Etiketteki içerikleri okumayı ve cildine uygun olanı seçmeyi öğren.
```html
<!-- wp:paragraph -->
<p>Raflardaki ürünlerin çoğu benzer vaatlerle satılır. Farkı yaratan, içindeki aktif içerikler ve bunların cildine uygun olup olmadığıdır.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Sık görülen içerikler ve ne işe yaradıkları</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong>Hyalüronik asit:</strong> cilde nem çeker, dolgun bir görünüm sağlar.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Niasinamid:</strong> gözenek görünümünü azaltır, ton eşitsizliğine iyi gelir.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Retinol:</strong> ince çizgiler ve doku için; akşam kullanılır.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>C vitamini:</strong> antioksidan; sabah güneş koruyucunun altına.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>AHA/BHA:</strong> ölü hücreleri arındırır; hassas ciltlerde dikkatli kullanılmalı.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Birlikte kullanırken dikkat</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Her aktifi aynı anda kullanmak cildi tahriş edebilir. Retinol ile asitleri aynı akşam kullanmaktan kaçın, yeni bir ürünü önce küçük bir bölgede dene.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Rutinini sadeleştir</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Temizleyici, nemlendirici ve güneş koruyucu temel üçlüdür. Aktif içerikleri bu temelin üzerine, cildinin ihtiyacına göre ekle. Ön görüşmede cilt tipine uygun bir rutin hazırlayabiliriz.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```

### Post: Güçlü ve Işıltılı Saçlar İçin Uzman İpuçları
- Category: **Saç Bakımı**
- Featured image: **b4**
- Excerpt: Saç derisinin sağlığından beslenmeye, parlak saçlar için günlük alışkanlıklar.
```html
<!-- wp:paragraph -->
<p>Sağlıklı saç, sağlıklı bir saç derisiyle başlar. Parlaklık ve güç için pahalı ürünlerden çok düzenli ve doğru alışkanlıklar önemlidir.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Saç derisini ihmal etme</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Saç derisini haftada birkaç kez parmak uçlarınla masaj yaparak yıkamak kan dolaşımını destekler. Yağlanma veya kepek sorunu varsa saç derisine uygun bir şampuan seç.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Isıdan koru</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Fön makinesi, maşa ve düzleştiriciyi kullanmadan önce ısı koruyucu uygula; ısıyı orta seviyede tut.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Beslenme ve dökülme</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Protein, demir, çinko ve biotin saç sağlığı için önemlidir. Dökülme uzun sürüyorsa altta yatan nedeni anlamak için bir uzmana danışmak gerekir.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Klinikte saç bakımı</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Saç derisine yönelik bakım uygulamaları, saçlarının daha güçlü ve canlı görünmesine destek olabilir. Sana uygun uygulamayı ön görüşmede belirleriz.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```

### Post: Dermal Dolguların Çok Yönlülüğünü Keşfetmek
- Category: **Güzellik Tedavileri**
- Featured image: **b5**
- Excerpt: Dudaktan çene hattına dolguların kullanım alanları, süreci ve doğal sonuç için dikkat edilenler.
```html
<!-- wp:paragraph -->
<p>Dermal dolgular, yaşla birlikte azalan hacmi yerine koymak ya da yüz hatlarını dengelemek için kullanılan, ameliyatsız uygulamalardır. Doğru planlandığında sonuç doğal ve dengeli görünür.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Nerelerde kullanılır?</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Dudak dolgunlaştırma ve dudak konturu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Yanak ve elmacık kemiği</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Çene hattı ve çene ucu</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Göz altı çukurları</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Şakak bölgesi ve marionette çizgileri</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Süreç nasıl işler?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Önce yüzün analiz edilir ve beklentilerin konuşulur. Uygulama genellikle kısa sürer; bölgesel uyuşturucu ile konfor sağlanır. Hafif şişlik veya morarma birkaç gün içinde geçer.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Ne kadar kalıcıdır?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kalıcılık kullanılan ürüne, bölgeye ve kişinin metabolizmasına göre değişir. Muayenede sana beklenen süreyi ve gerekirse rötuş zamanlamasını anlatırız.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```

### Post: Saç Kaynakları ve Dönüştürücü Gücü
- Category: **Saç Bakımı**
- Featured image: **b6**
- Excerpt: Saç kaynağı türleri, kimlere uygun olduğu ve bakımı hakkında bilmen gerekenler.
```html
<!-- wp:paragraph -->
<p>Saç kaynağı; daha uzun, daha hacimli ya da daha dolgun görünen saçlar için kullanılan pratik bir yöntemdir. Doğru teknik ve bakımla doğal bir görünüm sağlar.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Hangi türleri var?</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><strong>Mikro kaynak:</strong> küçük tutamlarla, doğal bir geçiş için.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Bant kaynak:</strong> hızlı uygulanır, ince saçlarda tercih edilir.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Klipsli kaynak:</strong> günlük takılıp çıkarılabilir, geçici bir çözüm.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Kimlere uygundur?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Saçında hacim veya uzunluk isteyen, ancak kendi saçı kaynağın ağırlığını taşıyabilecek durumda olan herkes için uygundur. Saç derisi hassasiyeti veya yoğun dökülme varsa önce değerlendirme yapılmalıdır.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Bakım ipuçları</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Kaynakları köklerden uzak tutarak tara, sülfatsız şampuan kullan ve ıslak saçla uyuma. Düzenli kontrol randevuları kaynakların uzun süre güzel görünmesini sağlar.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Bu yazı genel bilgilendirme amaçlıdır. Sana uygun uygulamayı belirlemek için uzmanımızla ön görüşme yapmanı öneririz.</em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cildin ya da saçın için doğru adımı birlikte planlayalım: <a href="https://fatihbora.net/test/iletisim/">ücretsiz ön görüşme al</a>.</p>
<!-- /wp:paragraph -->
```
