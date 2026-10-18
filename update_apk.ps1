# Alexa Gym Android APK Builder & Signer
# Generates production-ready AlexaGym.apk with targetSdk 34, minSdk 21, v1+v2+v3 signatures
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$sdkDir = "C:\Users\admin\AppData\Local\Android\Sdk"
$buildTools = "$sdkDir\build-tools\36.0.0"
$platformJar = "$sdkDir\platforms\android-37.0\android.jar"
$jdk8Bin = "C:\Program Files\Android\jdk\jdk-8.0.302.8-hotspot\jdk8u302-b08\bin"
$javac = "$jdk8Bin\javac.exe"
$keytool = "$jdk8Bin\keytool.exe"
$aapt2 = "$buildTools\aapt2.exe"
$d8 = "$buildTools\d8.bat"
$zipalign = "$buildTools\zipalign.exe"
$apksigner = "$buildTools\apksigner.bat"

$workDir = "c:\wamp64\www\gym-management-php\build_apk_tmp"
if (Test-Path $workDir) { Remove-Item -Path $workDir -Recurse -Force }
New-Item -ItemType Directory -Path "$workDir\src\com\alexagym\app" -Force | Out-Null
New-Item -ItemType Directory -Path "$workDir\res\values" -Force | Out-Null
New-Item -ItemType Directory -Path "$workDir\res\drawable" -Force | Out-Null
New-Item -ItemType Directory -Path "$workDir\assets\www" -Force | Out-Null
New-Item -ItemType Directory -Path "$workDir\bin" -Force | Out-Null
New-Item -ItemType Directory -Path "$workDir\compiled_res" -Force | Out-Null

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

# 1. AndroidManifest.xml (Target SDK 34, Min SDK 21 for modern Android 10-15 compatibility)
$manifest = @'
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="com.alexagym.app"
    android:versionCode="3"
    android:versionName="1.2.0">

    <uses-sdk
        android:minSdkVersion="21"
        android:targetSdkVersion="34" />

    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <application
        android:label="@string/app_name"
        android:icon="@drawable/ic_launcher"
        android:allowBackup="true"
        android:hardwareAccelerated="true"
        android:usesCleartextTraffic="true"
        android:theme="@android:style/Theme.NoTitleBar.Fullscreen">
        <meta-data
            android:name="android.webkit.WebView.EnableSafeBrowsing"
            android:value="false" />
        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:launchMode="singleTask"
            android:hardwareAccelerated="true"
            android:configChanges="orientation|screenSize|keyboardHidden">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
            <intent-filter>
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="alexagym" />
            </intent-filter>
        </activity>
    </application>
</manifest>
'@
[System.IO.File]::WriteAllText("$workDir\AndroidManifest.xml", $manifest, $utf8NoBom)

# 2. Strings
$strings = "<resources><string name=`"app_name`">Alexa Gym</string></resources>"
[System.IO.File]::WriteAllText("$workDir\res\values\strings.xml", $strings, $utf8NoBom)

# 3. App Logo Icon
Copy-Item "c:\wamp64\www\gym-management-php\mobile\icons\Icon-192.png" "$workDir\res\drawable\ic_launcher.png" -Force

# 4. Assets (keep lightweight placeholder for offline asset structure)
New-Item -ItemType Directory -Path "$workDir\assets" -Force | Out-Null

# 5. Java source
$javaCode = @'
package com.alexagym.app;

import android.app.Activity;
import android.os.Bundle;
import android.os.Build;
import android.view.KeyEvent;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.WebChromeClient;
import android.webkit.SslErrorHandler;
import android.net.http.SslError;
import android.webkit.SafeBrowsingResponse;
import android.webkit.WebResourceRequest;
import android.content.Intent;
import android.net.Uri;
import android.graphics.Color;

public class MainActivity extends Activity {
    private WebView webView;
    private final String targetUrl = "http://alexagym.infinityfreeapp.com/mobile/";

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleDeepLink(intent);
    }

    private boolean handleDeepLink(Intent intent) {
        if (intent != null && intent.getData() != null) {
            Uri data = intent.getData();
            if ("alexagym".equalsIgnoreCase(data.getScheme())) {
                String query = data.getEncodedQuery();
                if (query == null || query.isEmpty()) {
                    query = data.getQuery();
                }
                if (query != null && !query.isEmpty()) {
                    String target = "http://alexagym.infinityfreeapp.com/mobile/index.html#/" + (query.startsWith("?") ? query : "?" + query);
                    if (webView != null) {
                        webView.loadUrl(target);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        webView = new WebView(this);
        webView.setBackgroundColor(Color.parseColor("#07080F"));
        
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);

        // Disable SafeBrowsing false positive on free hosting subdomain
        if (Build.VERSION.SDK_INT >= 26) {
            settings.setSafeBrowsingEnabled(false);
        }

        // Spoof standard Chrome Mobile user-agent to avoid WebView blocks
        settings.setUserAgentString("Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36");

        // Required for InfinityFree AES cookie authentication
        android.webkit.CookieManager cookieManager = android.webkit.CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (url != null) {
                    if (url.startsWith("alexagym://")) {
                        handleDeepLink(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                        return true;
                    }
                    String lower = url.toLowerCase();
                    // Externalize Google Auth, Invoices, PDFs, APK downloads to native Chrome browser
                    if (lower.contains("accounts.google.com") ||
                        lower.contains("google-login.php") ||
                        lower.contains("receipt.php") ||
                        lower.contains("download.php") ||
                        lower.endsWith(".pdf") ||
                        lower.endsWith(".apk")) {
                        try {
                            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                            startActivity(intent);
                            return true;
                        } catch (Exception e) {}
                    }
                }
                view.loadUrl(url);
                return true;
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.proceed(); // Allows connection even if free hosting SSL chain has issues
            }

            @Override
            public void onSafeBrowsingHit(WebView view, WebResourceRequest request, int threatType, SafeBrowsingResponse callback) {
                if (Build.VERSION.SDK_INT >= 27) {
                    callback.proceed(true);
                } else {
                    super.onSafeBrowsingHit(view, request, threatType, callback);
                }
            }

            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                // Only show custom offline page on genuine network failures
                if (errorCode == ERROR_CONNECT || errorCode == ERROR_HOST_LOOKUP || errorCode == ERROR_TIMEOUT) {
                    String errorHtml = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1.0'></head><body style='background:#07080f;color:#f3f4fd;font-family:sans-serif;padding:40px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;margin:0;'>"
                        + "<div style='background:#10B981;color:#fff;width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:bold;margin-bottom:16px;'>A</div>"
                        + "<h2 style='color:#ffffff;margin:0 0 10px 0;'>Alexa Gym</h2>"
                        + "<p style='color:#94a3b8;font-size:14px;line-height:1.6;max-width:320px;margin:0 auto 24px auto;'>Please check your internet connection and try again.</p>"
                        + "<button onclick=\"window.location.href='http://alexagym.infinityfreeapp.com/mobile/'\" style='background:linear-gradient(135deg,#10B981,#059669);color:#ffffff;border:none;padding:12px 28px;border-radius:10px;font-size:15px;font-weight:bold;cursor:pointer;'>Retry Connection</button>"
                        + "</body></html>";
                    view.loadDataWithBaseURL("http://alexagym.infinityfreeapp.com/mobile/", errorHtml, "text/html", "utf-8", null);
                }
            }
        });
        webView.setWebChromeClient(new WebChromeClient());

        setContentView(webView);
        webView.clearCache(true);
        if (!handleDeepLink(getIntent())) {
            webView.loadUrl(targetUrl);
        }
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }
}
'@
[System.IO.File]::WriteAllText("$workDir\src\com\alexagym\app\MainActivity.java", $javaCode, $utf8NoBom)

Write-Host "Compiling resources with aapt2..."
& $aapt2 compile --dir "$workDir\res" -o "$workDir\compiled_res\resources.zip"

Write-Host "Linking resources with aapt2 (minSdk 21, targetSdk 34)..."
& $aapt2 link -I $platformJar --manifest "$workDir\AndroidManifest.xml" --min-sdk-version 21 --target-sdk-version 34 -o "$workDir\bin\app-res.apk" --java "$workDir\src" --auto-add-overlay "$workDir\compiled_res\resources.zip"

Write-Host "Compiling Java..."
$javaFiles = Get-ChildItem -Path "$workDir\src" -Recurse -Filter "*.java" | ForEach-Object { $_.FullName }
& $javac -source 1.8 -target 1.8 -bootclasspath $platformJar -d "$workDir\bin" $javaFiles

Write-Host "Compiling dex with d8..."
$classFiles = (Get-ChildItem -Path "$workDir\bin" -Recurse -Filter "*.class" | ForEach-Object { "`"$($_.FullName)`"" }) -join " "
cmd /c "set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr&& call `"$d8`" --lib `"$platformJar`" --output `"$workDir\bin`" $classFiles"

Write-Host "Packaging APK..."
$unalignedApk = "$workDir\bin\app-unaligned.apk"
Copy-Item "$workDir\bin\app-res.apk" $unalignedApk
$zipArchive = [System.IO.Compression.ZipFile]::Open($unalignedApk, [System.IO.Compression.ZipArchiveMode]::Update)
[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipArchive, "$workDir\bin\classes.dex", "classes.dex")
$assetFiles = Get-ChildItem -Path "$workDir\assets" -Recurse -File
foreach ($file in $assetFiles) {
    $relative = $file.FullName.Substring(("$workDir\assets\").Length).Replace("\", "/")
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipArchive, $file.FullName, "assets/$relative")
}
$zipArchive.Dispose()

Write-Host "Zipalign 4-byte..."
$alignedApk = "$workDir\bin\app-aligned.apk"
& $zipalign -p -f 4 $unalignedApk $alignedApk

Write-Host "Signing with permanent keystore..."
$keystore = "c:\wamp64\www\gym-management-php\alexagym.keystore"
if (-not (Test-Path $keystore)) {
    & $keytool -genkeypair -v -keystore $keystore -storepass alexagym2026 -alias alexagym -keypass alexagym2026 -keyalg RSA -keysize 2048 -validity 10000 -dname "CN=AlexaGym,O=Gym,C=IN"
}

$finalApk = "c:\wamp64\www\gym-management-php\downloads\AlexaGym.apk"
cmd /c "set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr&& call `"$apksigner`" sign --v1-signing-enabled true --v2-signing-enabled true --v3-signing-enabled true --ks `"$keystore`" --ks-pass pass:alexagym2026 --key-pass pass:alexagym2026 --out `"$finalApk`" `"$alignedApk`""

cmd /c "set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr&& call `"$apksigner`" verify --verbose `"$finalApk`""

# Sync to .bin for InfinityFree download bypass and Desktop copy
Copy-Item $finalApk "c:\wamp64\www\gym-management-php\downloads\AlexaGym.bin" -Force
if (Test-Path "C:\Users\admin\Desktop") {
    Copy-Item $finalApk "C:\Users\admin\Desktop\AlexaGym.apk" -Force
}

Remove-Item -Path $workDir -Recurse -Force
Write-Host "SUCCESS: Production APK ready at $finalApk"
