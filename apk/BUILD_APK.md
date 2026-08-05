# Building the Nanny-App APK

The Nanny-App backend is **PHP + MySQL**, so the Android app is a thin native
**WebView wrapper** around the live site (the standard way to ship a PHP app as
an APK). Two supported routes — pick one.

> **Admin is intentionally unavailable inside this app.** The server detects
> requests carrying the `NannyAppCordova` user-agent marker (set below via
> `AppendUserAgent`) or an installed-PWA cookie and blocks admin login/pages
> there, bouncing to `admin-unavailable.php` instead — admins must use a
> regular browser. See `is_native_app_request()` in `includes/functions.php`.
> **If you build with Route B (Capacitor) instead of the provided
> `config.xml`, you must add an equivalent user-agent override** (Capacitor's
> `appendUserAgent`/`overrideUserAgent` server option) or the server-side
> block won't recognize that build as the app.

> An APK cannot be produced from PHP/XAMPP alone — it requires the Android SDK
> + Java (JDK) + Gradle. Install **Android Studio** first (it bundles all three).

---

## Prerequisites (once)

1. Install **Android Studio** → during setup also install *Android SDK* and an
   *AVD* (virtual device) or enable USB debugging on a phone.
2. Install **Node.js** (https://nodejs.org).
3. Install **JDK 17** (Android Studio bundles one; set `JAVA_HOME` to it).
4. Set env vars: `ANDROID_HOME` → your SDK path, and add `platform-tools` to PATH.

---

## Route A — Apache Cordova (uses the provided `config.xml`)

```bash
# 1. Install Cordova
npm install -g cordova

# 2. Create a project and enter it
cordova create nanny-apk app.nanny.knights Nanny-App
cd nanny-apk

# 3. Replace the generated config.xml with the one in this folder
#    (copy apk/config.xml over nanny-apk/config.xml)

# 4. Add the Android platform and build
cordova platform add android
cordova build android            # debug APK
# or a release (unsigned) APK:
# cordova build android --release
```

The APK is written to:
`nanny-apk/platforms/android/app/build/outputs/apk/debug/app-debug.apk`

**Important — make the server reachable from the device:**
- Start XAMPP (Apache + MySQL) on your PC.
- Emulator: the host PC is `10.0.2.2`, so `config.xml` already points to
  `http://10.0.2.2/nannyapp/index.php`.
- Real phone on the same Wi-Fi: change `content src` and `allow-navigation`
  to your PC's LAN IP, e.g. `http://192.168.1.20/nannyapp/index.php`
  (find it with `ipconfig`). Allow port 80 through Windows Firewall.

Run on a connected device/emulator directly:
```bash
cordova run android
```

---

## Route B — Capacitor (modern alternative)

```bash
npm init -y
npm install @capacitor/core @capacitor/cli @capacitor/android
npx cap init Nanny-App app.nanny.knights --web-dir=www
mkdir www && echo "redirect" > www/index.html   # placeholder shell
npx cap add android
# In android/app/src/main/AndroidManifest.xml allow cleartext (http) traffic,
# and set the server URL in capacitor.config.json:
#   "server": { "url": "http://10.0.2.2/nannyapp", "cleartext": true }
npx cap open android        # opens Android Studio -> Build > Build APK(s)
```

---

## Signing a release APK (for Play Store / sharing)

```bash
keytool -genkey -v -keystore nanny.keystore -alias nanny -keyalg RSA -keysize 2048 -validity 10000
# Build --release, then:
zipalign -v 4 app-release-unsigned.apk nanny-aligned.apk
apksigner sign --ks nanny.keystore --out nanny-app.apk nanny-aligned.apk
```

---

## Notes
- For production, host the PHP app on a real server with **HTTPS** and point
  `content src` at that URL — then no cleartext/IP juggling is needed.
- The app is also an installable **PWA**: open the site in Chrome on Android →
  menu → *Install app / Add to Home screen*. That gives an app-like icon with
  no build step, useful for quick demos.
- Rebuilding after a server-side update (new `config.xml` version, updated
  `service-worker.js` cache name) is enough to ship the change — the WebView
  always loads the live site, so most updates don't need a new APK at all.
  Only bump/rebuild the APK itself when `config.xml` (permissions, min SDK,
  splash, etc.) changes.

## Changelog
- **1.1.0** — Admin dashboard now blocked inside the packaged app/installed
  PWA (web-browser-only by design). Escrow-style payment holds with a
  parent-issued check-in PIN before a nanny is paid out. Admin profile
  records.
- **1.0.0** — Initial release.
