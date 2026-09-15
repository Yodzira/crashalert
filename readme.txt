=== CrashAlert ===
Contributors: yodsira
Tags: fatal error, error monitoring, telegram, debugging, alerts
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fatal error alerts to Telegram, email and webhook — with plain-language explanations, culprit attribution and a recovery notice.

== Description ==

Know about a crash before your clients do.

When a plugin or theme throws a fatal error, WordPress emails the site owner —
hours later, easily lost in spam, and always in cryptic PHP jargon. CrashAlert
makes the same information instant, readable and actionable:

* **Instant alerts** to Telegram, email and a custom webhook.
* **Culprit attribution** — the alert names the plugin, mu-plugin or theme
  that owns the failing file (with its version).
* **Plain-language explanations** — "Call to undefined function" becomes
  "Plugin versions diverged — roll back the last update", in English or Russian.
* **Recovery notice** — a green message when the site starts responding again.
* **Correlation with updates** — "what changed in the 24 h before the crash?"
  is answered right in the event card.
* **Rate-limited** — a flood of identical errors produces one alert with a
  repeat counter, not a hundred messages.
* **Failure history** with a filterable timeline, kept for your chosen
  retention period (default 60 days) and fully removed on uninstall.
* **Privacy first** — nothing is sent to third parties, no IPs stored,
  stack traces sanitized (relative paths, no call arguments).

CrashAlert never limits your site: the monitor itself is guarded so it can
never be the cause of a white screen, and healthy requests pay a microsecond
overhead.

Looking for more? The optional CrashAlert Pro companion adds a PHP
deprecation radar (warnings that will become fatals in future PHP versions),
unlimited alert channels and a weekly health report: https://yodsira.com/buy/crashalert

= Why not just rely on the WordPress core email? =

Core recovery emails go to the site owner's address, are easy to miss, contain
a raw stack trace, say nothing about repeats, and never tell you the site is
back. CrashAlert covers exactly those gaps — and delivers to Telegram, where
site owners actually live.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip via
   Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Open CrashAlert → Settings, paste your Telegram bot token and chat id,
   press "Send test" — done. Email alerts to the admin address are on by
   default.

There is no easier setup. No external accounts, no cloud, no telemetry.

== Frequently Asked Questions ==

= Will it slow my site down? =

Healthy requests do not touch the database at all — the monitor checks the
last PHP error in the shutdown phase and exits. Fatal handling is capped
(sanitized stack, queue) and alerts are sent by cron, not during the request.

= WordPress already emails me about fatals. Why this plugin? =

Speed (Telegram beats email), attribution (named plugin and version), human
explanations, repeat counting, recovery notices and a searchable history.
Core emails stop at "something died, here is a stack".

= Will I get spammed if a loop throws 500 fatals a minute? =

No. Identical errors share one signature; one alert goes out per signature
per rate window (default 10 minutes) and repeats are counted ("×500").

= Does it send my data anywhere? =

No. Alerts go only to the channels you configure. No IPs are stored, stack
traces are sanitized, and uninstall removes everything.

== Changelog ==

= 0.1.0 =
* Initial release: fatal capture, culprit attribution, plain-language
  explanations (RU/EN), Telegram/email/webhook alerts, rate limiting,
  recovery notices, update correlation, event timeline, retention.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
