# Building the Nanny-App APK

The Nanny-App backend is **PHP + MySQL**, so the Android app is a thin native
**WebView wrapper** around the live site (the standard way to ship a PHP app as
an APK). Two supported routes — pick one.

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
