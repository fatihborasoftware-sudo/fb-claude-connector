# 2 — Build kit prompt

Send this in the same chat once you approve the mockup. Claude turns the mockup into one file with everything the build needs. The Nova result is [`../Nova-build-kit.md`](../Nova-build-kit.md).

## Türkçe

```
Mockup'u onaylıyorum. Şimdi bunu FB AI Engine – Claude Connector ile kurulacak şekilde hazırla:

1. Tek bir build kit dosyası (Markdown): build planı (sayfalar, blog yazıları, tema, eklentiler, çöp kutusuna taşınacak varsayılan içerik), görsel listesi (adres + alt metin), Kadence renk/yazı tipi/header/footer ayarları, site CSS'i, iletişim formu, footer widget'ları, menü ve her sayfa ile her blog yazısı için hazır WordPress blok kodu.
2. Görseller için {{img:ANAHTAR}} yer tutucusu kullan; sayfalara <style> etiketi koyma, stiller site CSS'inde olsun.
3. Yeni bir sohbette bu kiti adım adım kuracak bir prompt yaz: önce build plan, sonra WPvivid + yedek, tema, eklentiler, temizlik, görseller, tasarım, form, sayfalar, yazılar, menü, footer ve site kontrolü.

Sitemin adresi: [SİTE ADRESİ]
```

## English

```
I approve the mockup. Now prepare it to be built with FB AI Engine – Claude Connector:

1. One build kit file (Markdown): the build plan (pages, blog posts, theme, plugins, default content to move to the Trash), the image list (address + alt text), the Kadence colour/font/header/footer settings, the site CSS, the contact form, the footer widgets, the menu, and ready WordPress block markup for every page and every blog post.
2. Use a {{img:KEY}} placeholder for images; put no <style> tags in pages — styles go in the site CSS.
3. Write a prompt that builds this kit step by step in a new chat: build plan first, then WPvivid + backup, theme, plugins, clean-up, images, design, form, pages, posts, menu, footer and the site check.

My site address: [SITE ADDRESS]
```
