<?php
/**
 * Interface language (1.1.0): English or Türkçe for the connector's own screens.
 *
 * Each administrator picks a language on Claude Connection (or "Auto" = follow the
 * WordPress profile language). Screens are rendered in English and translated on the
 * way out — only text that matches a known label exactly is replaced, so content,
 * page titles and log entries are never touched.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FBCC_I18n {

	const META = 'fbcc_lang';

	public static function init() {
		add_action( 'admin_post_fbcc_lang', array( __CLASS__, 'action_lang' ) );
	}

	/** 'en' or 'tr' for the current user. */
	public static function lang() {
		static $lang = null;
		if ( null !== $lang ) {
			return $lang;
		}
		$pick = get_current_user_id() ? (string) get_user_meta( get_current_user_id(), self::META, true ) : '';
		if ( 'tr' === $pick || 'en' === $pick ) {
			$lang = $pick;
		} else {
			$loc  = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$lang = 0 === strpos( (string) $loc, 'tr' ) ? 'tr' : 'en';
		}
		return $lang;
	}

	public static function pick() {
		$p = get_current_user_id() ? (string) get_user_meta( get_current_user_id(), self::META, true ) : '';
		return in_array( $p, array( 'en', 'tr' ), true ) ? $p : 'auto';
	}

	public static function action_lang() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'fbcc_lang' );
		$l = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : 'auto';
		if ( in_array( $l, array( 'en', 'tr' ), true ) ) {
			update_user_meta( get_current_user_id(), self::META, $l );
		} else {
			delete_user_meta( get_current_user_id(), self::META );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=fbcc' ) );
		exit;
	}

	/** Small "English · Türkçe" switch for the screen header. */
	public static function switcher() {
		$cur = self::pick();
		$out = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="fbcc-lang">';
		$out .= '<input type="hidden" name="action" value="fbcc_lang">' . wp_nonce_field( 'fbcc_lang', '_wpnonce', true, false );
		$out .= '<label for="fbcc-lang-sel" class="screen-reader-text">Interface language</label>';
		$out .= '<select id="fbcc-lang-sel" name="lang" onchange="this.form.submit()">';
		foreach ( array( 'auto' => 'Auto (profile language)', 'en' => 'English', 'tr' => 'Türkçe' ) as $k => $v ) {
			$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		$out .= '</select><noscript><button class="button">OK</button></noscript></form>';
		return $out;
	}

	/** Translate one label (used by PHP code that builds text itself). */
	public static function t( $s ) {
		if ( 'tr' !== self::lang() ) {
			return $s;
		}
		$d = self::dict();
		return $d[ $s ] ?? $s;
	}

	/** Translate rendered HTML: exact text nodes and title / aria-label / placeholder values. */
	public static function html( $html ) {
		if ( 'tr' !== self::lang() || '' === $html ) {
			return $html;
		}
		$d    = self::dict();
		$pats = self::patterns();
		$one  = function ( $raw ) use ( $d, $pats ) {
			$dec = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$key = trim( preg_replace( '/\s+/u', ' ', $dec ) );
			if ( '' === $key ) {
				return null;
			}
			if ( isset( $d[ $key ] ) ) {
				return $d[ $key ];
			}
			foreach ( $pats as $re => $to ) {
				if ( preg_match( $re, $key ) ) {
					return preg_replace( $re, $to, $key );
				}
			}
			return null;
		};
		// Never touch scripts, styles, code or form values.
		$parts = preg_split( '#(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>|<code\b.*?</code>|<pre\b.*?</pre>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $p ) {
			if ( $i % 2 ) {
				continue;
			}
			$p = preg_replace_callback( '/>([^<>]+)</u', function ( $m ) use ( $one ) {
				$t = $one( $m[1] );
				if ( null === $t ) {
					return $m[0];
				}
				preg_match( '/^\s*/u', $m[1], $lead );
				preg_match( '/\s*$/u', $m[1], $tail );
				return '>' . $lead[0] . esc_html( $t ) . $tail[0] . '<';
			}, $p );
			$p = preg_replace_callback( '/\b(title|aria-label|placeholder)="([^"]*)"/u', function ( $m ) use ( $one ) {
				$t = $one( $m[2] );
				return null === $t ? $m[0] : $m[1] . '="' . esc_attr( $t ) . '"';
			}, $p );
			$parts[ $i ] = $p;
		}
		return implode( '', $parts );
	}

	/** Renders a screen callback through the translator. */
	public static function render( $callback ) {
		ob_start();
		call_user_func( $callback );
		echo self::html( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput -- already-escaped screen HTML
	}

	/** Strings for assets/live.js. */
	public static function js() {
		if ( 'tr' !== self::lang() ) {
			return new stdClass();
		}
		return array(
			'Read-only'                                                    => 'Salt okunur',
			'Content editor'                                               => 'İçerik editörü',
			'Site maintainer'                                              => 'Site yöneticisi',
			'Following Claude'                                             => 'Claude’u izliyor',
			'Browsing freely'                                              => 'Serbest geziniyorsunuz',
			'CLAUDE CHANGED THIS'                                          => 'CLAUDE BUNU DEĞİŞTİRDİ',
			'Claude just updated %s'                                       => 'Claude az önce güncelledi: %s',
			'the site settings'                                            => 'site ayarları',
			'the whole site'                                               => 'tüm site',
			'Network error — try again.'                                   => 'Ağ hatası — tekrar deneyin.',
			'Approve all %s in order'                                      => '%s öğenin tümünü sırayla onayla',
			'Click again to approve all %s'                                => '%s öğenin tümünü onaylamak için tekrar tıklayın',
			'Approving…'                                                   => 'Onaylanıyor…',
			'Rejecting…'                                                   => 'Reddediliyor…',
			'View diff ↗'                                                  => 'Farkları gör ↗',
			'Reject'                                                       => 'Reddet',
			'Approve'                                                      => 'Onayla',
			'Approve (locked)'                                             => 'Onayla (kilitli)',
			'Approved plan #%s: %s'                                        => 'Onaylı plan #%s: %s',
			'%s/%s pages built · %s live · go live %s'                     => '%s/%s sayfa hazır · %s yayında · yayına alma: %s',
			'automatically'                                                => 'otomatik',
			'with one Launch click'                                        => 'tek bir Yayınla tıklamasıyla',
			'Inside this plan Claude does not need to ask you again.'      => 'Bu planın içinde Claude size tekrar sormaz.',
			'Launch %s item(s) now'                                        => '%s öğeyi şimdi yayına al',
			'Launching…'                                                   => 'Yayına alınıyor…',
			'STEP'                                                         => 'ADIM',
			'Working'                                                      => 'Çalışıyor',
			'Nothing is running. Start a task in Claude and watch it here.' => 'Şu an çalışan bir iş yok. Claude’da bir görev başlatın ve burada izleyin.',
			'Waiting for a task'                                           => 'Görev bekleniyor',
			'Plan #%s · '                                                  => 'Plan #%s · ',
			' · write lock on'                                             => ' · yazma kilidi açık',
			' · lab lock on'                                               => ' · lab kilidi açık',
			'LIVE'                                                         => 'CANLI',
			'IDLE'                                                         => 'BOŞTA',
			'done'                                                         => 'tamam',
			'read'                                                         => 'okundu',
			'waiting for you'                                              => 'sizi bekliyor',
			'ready to launch'                                              => 'yayına hazır',
			'blocked'                                                      => 'engellendi',
			'error'                                                        => 'hata',
			'info'                                                         => 'bilgi',
			'browser'                                                      => 'tarayıcı',
			'Admin'                                                        => 'Yönetim',
		);
	}

	private static function patterns() {
		return array(
			'/^Approvals \((\d+)\)$/u'                                 => 'Onaylar ($1)',
			'/^Build plan #(\d+): (.+)$/u'                             => 'Yapım planı #$1: $2',
			'/^Go live: automatically · until (.+)$/u'                 => 'Yayına alma: otomatik · $1 tarihine kadar',
			'/^Go live: with one Launch click · until (.+)$/u'         => 'Yayına alma: tek bir Yayınla tıklamasıyla · $1 tarihine kadar',
			'/^(\d+) reads · (\d+) changes · (\d+) waiting · (\d+) blocked$/u' => '$1 okuma · $2 değişiklik · $3 bekleyen · $4 engellenen',
			'/^— last call (.+) ago$/u'                                => '— son çağrı: $1 önce',
			'/^OAuth sign-in, expires (.+) from now unless used$/u'    => 'OAuth girişi — kullanılmazsa $1 sonra sona erer',
			'/^OAuth sign-in$/u'                                       => 'OAuth girişi',
			'/^(.+) — approved by (.+)$/u'                             => '$1 — onaylayan: $2',
			'/^Plugin: (.+)$/u'                                        => 'Eklenti: $1',
			'/^built · (.+)$/u'                                        => 'hazır · $1',
			'/^🚀 Launch (\d+) item\(s\)$/u'                           => '🚀 $1 öğeyi yayına al',
			'/^When the task finishes, or at (.+)$/u'                  => 'Görev bitince veya saat $1’de',
			'/^Approve \($/u'                                          => 'Onayla (',
		);
	}

	/** Exact labels → Türkçe. */
	public static function dict() {
		static $d = null;
		if ( null !== $d ) {
			return $d;
		}
		$d = array(
			// Header + tabs
			'Claude Connection'                 => 'Claude Bağlantısı',
			'Connected'                         => 'Bağlı',
			'Not connected'                     => 'Bağlı değil',
			'Switched off'                      => 'Kapalı',
			'Read-only'                         => 'Salt okunur',
			'Content editor'                    => 'İçerik editörü',
			'Site maintainer'                   => 'Site yöneticisi',
			'▶ Watch Claude live'               => '▶ Claude’u canlı izle',
			'Overview'                          => 'Genel bakış',
			'Permissions & tools'               => 'İzinler ve araçlar',
			'Activity log'                      => 'Etkinlik kaydı',
			'Setup'                             => 'Kurulum',
			'Interface language'                => 'Arayüz dili',
			'Auto (profile language)'           => 'Otomatik (profil dili)',
			// Build plan card
			'Active'                            => 'Etkin',
			'Launched'                          => 'Yayına alındı',
			'live'                              => 'yayında',
			'not built yet'                     => 'henüz yapılmadı',
			'Main menu'                         => 'Ana menü',
			'in plan'                           => 'planda',
			'Homepage & site title'             => 'Ana sayfa ve site başlığı',
			'Header, footer, colours & fonts'   => 'Üst bilgi, alt bilgi, renkler ve yazı tipleri',
			'installed'                         => 'kurulu',
			'End plan'                          => 'Planı bitir',
			'Approved mockups ↗'                => 'Onaylanan tasarımlar ↗',
			// Browser card
			'Follow Claude’s browser'           => 'Claude’un tarayıcısını izle',
			'Linked'                            => 'Bağlandı',
			'Not linked'                        => 'Bağlı değil',
			'Linked since'                      => 'Bağlanma zamanı',
			'Signed in as'                      => 'Giriş yapan',
			'Mirroring'                         => 'Yansıtılan',
			'Pages and clicks · live screen'    => 'Sayfalar ve tıklamalar · canlı ekran',
			'Ends'                              => 'Bitiş',
			'— this is the linked browser'      => '— bağlı tarayıcı bu',
			'this is the linked browser'        => 'bağlı tarayıcı bu',
			'Unlink now'                        => 'Bağlantıyı şimdi kes',
			'Claude opens this page in its own browser and clicks the button below before it works on a screen the connector has no tool for. From then on Watch Me Live shows that browser: the page it is on, each click and save, and a live copy of the screen.' => 'Claude, bağlayıcının aracı olmayan bir ekranda çalışmadan önce bu sayfayı kendi tarayıcısında açar ve aşağıdaki düğmeye tıklar. Bundan sonra Canlı İzle o tarayıcıyı gösterir: bulunduğu sayfa, her tıklama ve kayıt, ve ekranın canlı bir kopyası.',
			'Link this browser as Claude’s'     => 'Bu tarayıcıyı Claude’un tarayıcısı olarak bağla',
			'Open the link page'                => 'Bağlantı sayfasını aç',
			'— this is the page Claude opens:'  => '— Claude’un açtığı sayfa:',
			'Only the linked browser reports'   => 'Yalnızca bağlı tarayıcı bildirir',
			'Your own browser is never mirrored' => 'Sizin tarayıcınız asla yansıtılmaz',
			'Only pages on this site'           => 'Yalnızca bu sitedeki sayfalar',
			'Other sites show as “outside the site”' => 'Diğer siteler “site dışında” olarak görünür',
			'Private by design'                 => 'Tasarımdan gizli',
			'Passwords, hidden fields and scripts are never copied' => 'Şifreler, gizli alanlar ve betikler asla kopyalanmaz',
			'This browser is now linked as Claude’s. Watch Me Live follows it.' => 'Bu tarayıcı artık Claude’un tarayıcısı olarak bağlı. Canlı İzle onu takip ediyor.',
			'Claude’s browser is unlinked.'     => 'Claude’un tarayıcı bağlantısı kesildi.',
			// Connection card
			'Connection'                        => 'Bağlantı',
			'Status'                            => 'Durum',
			'● Connected'                       => '● Bağlı',
			'Not connected yet — see Setup'     => 'Henüz bağlı değil — Kurulum sekmesine bakın',
			'Client'                            => 'İstemci',
			'Endpoint'                          => 'Uç nokta',
			'Auth'                              => 'Kimlik doğrulama',
			'Acts as'                           => 'Çalıştığı kullanıcı',
			'WordPress user'                    => 'WordPress kullanıcısı',
			'(Editor role, cannot sign in)'     => '(Editör rolü, giriş yapamaz)',
			'Today'                             => 'Bugün',
			'Copy endpoint URL'                 => 'Uç nokta adresini kopyala',
			'Copied'                            => 'Kopyalandı',
			'Revoke all sign-ins'               => 'Tüm girişleri iptal et',
			// Safety card
			'Safety'                            => 'Güvenlik',
			'AI Engine emergency write lock'    => 'AI Engine acil yazma kilidi',
			'ON — no changes allowed'           => 'AÇIK — hiçbir değişikliğe izin yok',
			'Set, but ignored by your choice'   => 'Tanımlı, ama sizin tercihinizle yok sayılıyor',
			'Off'                               => 'Kapalı',
			'ON'                                => 'AÇIK',
			'ON — Online Lab, always'           => 'AÇIK — Online Lab, her zaman',
			'Lab lock (plugins & site changes)' => 'Lab kilidi (eklentiler ve site değişiklikleri)',
			'Live content, publishing, plugin updates' => 'Yayındaki içerik, yayınlama, eklenti güncellemeleri',
			'Always need your approval'         => 'Her zaman onayınızı gerektirir',
			'Undo'                              => 'Geri alma',
			'Revision saved before every edit'  => 'Her düzenlemeden önce revizyon kaydedilir',
			'Disconnect Claude now (kill switch)' => 'Claude’un bağlantısını şimdi kes (acil durdurma)',
			'Switch the connector back on'      => 'Bağlayıcıyı yeniden aç',
			// Level form
			'Permission level'                  => 'İzin seviyesi',
			'Takes effect on Claude\'s next call.' => 'Claude’un bir sonraki çağrısında geçerli olur.',
			'Read pages, posts, plugins and site health. Changes nothing.' => 'Sayfaları, yazıları, eklentileri ve site sağlığını okur. Hiçbir şeyi değiştirmez.',
			'Plus: create drafts and edit drafts. Changes to live pages and publishing go to approvals.' => 'Ayrıca: taslak oluşturur ve düzenler. Yayındaki sayfalardaki değişiklikler ve yayınlama onaya gider.',
			'Plus: request plugin updates. Every one goes to approvals. Blocked while the lab lock is on.' => 'Ayrıca: eklenti güncellemesi ister. Her biri onaya gider. Lab kilidi açıkken engellenir.',
			'Save level'                        => 'Seviyeyi kaydet',
			// Activity
			'Recent activity'                   => 'Son etkinlikler',
			'Full log'                          => 'Tüm kayıt',
			'Time'                              => 'Zaman',
			'Tool'                              => 'Araç',
			'What happened'                     => 'Ne oldu',
			'Result'                            => 'Sonuç',
			'Info'                              => 'Bilgi',
			'Done'                              => 'Tamam',
			'Read'                              => 'Okundu',
			'Queued'                            => 'Sırada',
			'Blocked'                           => 'Engellendi',
			'Error'                             => 'Hata',
			'Ready'                             => 'Hazır',
			'Open'                              => 'Aç',
			'Restore revision'                  => 'Revizyonu geri yükle',
			'Older'                             => 'Daha eski',
			'Newer'                             => 'Daha yeni',
			// Tools tab
			'Tools Claude can use'              => 'Claude’un kullanabileceği araçlar',
			'Switch single tools off without changing the level.' => 'Seviyeyi değiştirmeden tek tek araçları kapatın.',
			'Save tools'                        => 'Araçları kaydet',
			'FB Software AI Engine locks'       => 'FB Software AI Engine kilitleri',
			'Save locks'                        => 'Kilitleri kaydet',
			'Lab lock'                          => 'Lab kilidi',
			'Block plugin updates and other site-level changes, even with approval' => 'Eklenti güncellemelerini ve diğer site düzeyindeki değişiklikleri, onaylansa bile engelle',
			'Save'                              => 'Kaydet',
			// Approvals tab
			'Waiting for your approval'         => 'Onayınızı bekleyenler',
			'Claude prepared these. Nothing happens until you approve.' => 'Bunları Claude hazırladı. Siz onaylayana kadar hiçbir şey olmaz.',
			'Nothing waiting.'                  => 'Bekleyen bir şey yok.',
			'Decided'                           => 'Karara bağlananlar',
			'Request'                           => 'İstek',
			'Decision'                          => 'Karar',
			'When'                              => 'Ne zaman',
			'Approved'                          => 'Onaylandı',
			'Rejected'                          => 'Reddedildi',
			'Failed'                            => 'Başarısız',
			'Approve'                           => 'Onayla',
			'Reject'                            => 'Reddet',
			'View diff'                         => 'Farkları gör',
			// Setup tab
			'Connect Claude to this site'       => 'Claude’u bu siteye bağlayın',
			'Choose a permission level on the Overview tab. Start with' => 'Genel bakış sekmesinde bir izin seviyesi seçin. Şununla başlayın:',
			'In Claude, open'                   => 'Claude’da şunu açın:',
			'Settings → Connectors → Add custom connector' => 'Ayarlar → Bağlayıcılar → Özel bağlayıcı ekle',
			'Name:'                             => 'Ad:',
			'— URL:'                            => '— Adres:',
			'Copy'                              => 'Kopyala',
			'. Leave the OAuth fields empty.'   => '. OAuth alanlarını boş bırakın.',
			'Click'                             => 'Tıklayın:',
			'Connect'                           => 'Bağlan',
			'. You land on a page of this site: sign in as an administrator and click' => '. Bu sitenin bir sayfasına gelirsiniz: yönetici olarak giriş yapın ve tıklayın:',
			'Allow Claude'                      => 'Claude’a izin ver',
			'Back here the status shows'        => 'Buraya döndüğünüzde durum şunu gösterir:',
			'. Every call appears in the Activity log.' => '. Her çağrı Etkinlik kaydında görünür.',
			'Server check'                      => 'Sunucu kontrolü',
			'Run server check'                  => 'Sunucu kontrolünü çalıştır',
			'Re-apply .htaccess fix'            => '.htaccess düzeltmesini yeniden uygula',
			'Full connection test'              => 'Tam bağlantı testi',
			'Signs in with a 2-minute test key and calls the endpoint through the public address, exactly like Claude does.' => '2 dakikalık bir test anahtarıyla giriş yapar ve uç noktayı Claude’un yaptığı gibi herkese açık adres üzerinden çağırır.',
			'Run full test'                     => 'Tam testi çalıştır',
			'Recent requests from Claude'       => 'Claude’dan gelen son istekler',
			'Last 40 requests to the sign-in and connector addresses (UTC). No keys are stored.' => 'Giriş ve bağlayıcı adreslerine gelen son 40 istek (UTC). Hiçbir anahtar saklanmaz.',
			'Where'                             => 'Nerede',
			'Method'                            => 'Yöntem',
			'Details'                           => 'Ayrıntılar',
			'Check the endpoints'               => 'Uç noktaları kontrol edin',
			'to WordPress.'                     => 'WordPress’e.',
			// Watch Me Live
			'IDLE'                              => 'BOŞTA',
			'LIVE'                              => 'CANLI',
			'Nothing is running. Start a task in Claude and watch it here.' => 'Şu an çalışan bir iş yok. Claude’da bir görev başlatın ve burada izleyin.',
			'calls'                             => 'çağrı',
			'tok'                               => 'token',
			'waiting'                           => 'bekleyen',
			'Voice off'                         => 'Ses kapalı',
			'Voice on'                          => 'Ses açık',
			'Voice briefs'                      => 'Sesli özetler',
			'On'                                => 'Açık',
			'Your browser reads Claude’s briefs aloud while you watch. Off by default; remembered on this computer.' => 'Siz izlerken tarayıcınız Claude’un özetlerini sesli okur. Varsayılan olarak kapalı; bu bilgisayarda hatırlanır.',
			'WHAT TO READ'                      => 'NE OKUNSUN',
			'Start brief'                       => 'Başlangıç özeti',
			'— what Claude is about to do'      => '— Claude’un ne yapacağı',
			'Each step'                         => 'Her adım',
			'— backup, pages, blog, theme, menu' => '— yedek, sayfalar, blog, tema, menü',
			'Waiting for you'                   => 'Sizi bekleyenler',
			'— an approval'                     => '— bir onay',
			'Finish brief'                      => 'Bitiş özeti',
			'— what was done, what is left'     => '— ne yapıldı, ne kaldı',
			'Every click'                       => 'Her tıklama',
			'— browser actions too (chatty)'    => '— tarayıcı işlemleri de (konuşkan)',
			'Language'                          => 'Dil',
			'Voice'                             => 'Ses',
			'Browser default'                   => 'Tarayıcı varsayılanı',
			'Speed ·'                           => 'Hız ·',
			'Claude writes its briefs in this language from the next task on.' => 'Claude bir sonraki görevden itibaren özetlerini bu dilde yazar.',
			'Test the voice'                    => 'Sesi dene',
			'Exit'                              => 'Çıkış',
			'Claude’s browser · mirrored live'  => 'Claude’un tarayıcısı · canlı yansıtılıyor',
			'Following Claude'                  => 'Claude’u izliyor',
			'New tab ↗'                         => 'Yeni sekme ↗',
			'Show me'                           => 'Göster',
			'CLAUDE IS IN'                      => 'CLAUDE ŞURADA',
			'Mind Map'                          => 'Zihin Haritası',
			'Pages'                             => 'Sayfalar',
			'Media'                             => 'Medya',
			'Approvals'                         => 'Onaylar',
			'Site'                              => 'Site',
			'Admin'                             => 'Yönetim',
			'Now'                               => 'Şimdi',
			'Feed'                              => 'Akış',
			'SPEAKING NOW'                      => 'ŞU AN OKUNUYOR',
			'Repeat'                            => 'Tekrarla',
			'Skip'                              => 'Geç',
			'THE PLAN'                          => 'PLAN',
			'Waiting for a task'                => 'Görev bekleniyor',
			'LAST ACTIONS'                      => 'SON İŞLEMLER',
			'Nothing is waiting for you.'       => 'Sizi bekleyen bir şey yok.',
			'Approving reloads the site on the left and highlights what changed.' => 'Onayladığınızda soldaki site yenilenir ve değişenler vurgulanır.',
			'Kill switch'                       => 'Acil durdurma',
			'Back'                              => 'Geri',
			'Forward'                           => 'İleri',
			'Reload'                            => 'Yenile',
			'Your website — click around while Claude works' => 'Siteniz — Claude çalışırken gezinebilirsiniz',
			'Claude’s browser — live copy (view only)' => 'Claude’un tarayıcısı — canlı kopya (yalnızca görüntüleme)',
			'Dismiss'                           => 'Kapat',
			'Where Claude is working'           => 'Claude’un çalıştığı yer',
			// Sign-in pages
			'Connection request not valid'      => 'Bağlantı isteği geçersiz',
			'This request did not come from a registered client, or its return address does not match. Nothing was shared.' => 'Bu istek kayıtlı bir istemciden gelmedi ya da dönüş adresi eşleşmiyor. Hiçbir şey paylaşılmadı.',
			'Administrator needed'              => 'Yönetici gerekli',
			'Only a site administrator can connect Claude to this site.' => 'Claude’u bu siteye yalnızca bir site yöneticisi bağlayabilir.',
			'Claude is working'                 => 'Claude çalışıyor',
			'step %1$s of %2$s'                 => 'adım %1$s / %2$s',
			'Claude · %s waiting for approval'  => 'Claude · %s onay bekliyor',
			// Site check card (1.1.0)
			'Site check'                        => 'Site kontrolü',
			'Run site check'                    => 'Site kontrolünü çalıştır',
			'Last check'                        => 'Son kontrol',
			'No check yet.'                     => 'Henüz kontrol yapılmadı.',
			'Pages checked'                     => 'Kontrol edilen sayfa',
			'Problems'                          => 'Sorunlar',
			'Warnings'                          => 'Uyarılar',
			'Page'                              => 'Sayfa',
			'Issue'                             => 'Sorun',
			'All clear — nothing to fix.'       => 'Her şey yolunda — düzeltilecek bir şey yok.',
			'Visitors see an old cached copy'   => 'Ziyaretçiler eski, önbellekteki bir kopyayı görüyor',
			'Broken link'                       => 'Kırık bağlantı',
			'Broken image'                      => 'Kırık görsel',
			'Image without alt text'            => 'Alt metni olmayan görsel',
			'No H1 heading on the page'         => 'Sayfada H1 başlık yok',
			'Blog post has no featured image'   => 'Blog yazısının öne çıkan görseli yok',
			'Shortcode shows as text'           => 'Kısa kod metin olarak görünüyor',
			'CSS code shows as text'            => 'CSS kodu metin olarak görünüyor',
			'Block code shows as text'          => 'Blok kodu metin olarak görünüyor',
			'Empty page <title>'                => 'Sayfa <title> boş',
			'Checks every published page and post: broken links and images, missing alt text, missing featured images, code or shortcodes showing as text, and page titles.' => 'Yayındaki her sayfa ve yazıyı kontrol eder: kırık bağlantılar ve görseller, eksik alt metin, eksik öne çıkan görseller, metin olarak görünen kod veya kısa kodlar ve sayfa başlıkları.',
			// Backups card (1.1.0)
			'Backups'                           => 'Yedekler',
			'Latest backup'                     => 'Son yedek',
			'No backup found.'                  => 'Yedek bulunamadı.',
			'A backup is running…'              => 'Bir yedek alınıyor…',
			'Take a backup now'                 => 'Şimdi yedek al',
			'Fresh'                             => 'Güncel',
			'Old'                               => 'Eski',
		);
		return $d;
	}
}
