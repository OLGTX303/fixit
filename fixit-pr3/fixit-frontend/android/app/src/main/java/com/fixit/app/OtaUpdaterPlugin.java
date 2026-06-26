package com.fixit.app;

import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import androidx.core.content.FileProvider;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

@CapacitorPlugin(name = "OtaUpdater")
public class OtaUpdaterPlugin extends Plugin {

    private static final String APK_PREFIX = "fixit-update-";

    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        String url = call.getString("url");
        String versionCode = call.getString("versionCode");
        if (url == null || url.isEmpty()) {
            call.reject("url is required");
            return;
        }
        if (versionCode == null || versionCode.isEmpty()) {
            call.reject("versionCode is required");
            return;
        }

        new Thread(() -> {
            try {
                purgeCachedUpdates();
                File apk = new File(getContext().getCacheDir(), APK_PREFIX + versionCode + ".apk");
                downloadApk(url, apk);
                getActivity().runOnUiThread(() -> {
                    try {
                        promptInstall(apk);
                        call.resolve();
                    } catch (Exception e) {
                        call.reject("Install failed: " + e.getMessage());
                    }
                });
            } catch (Exception e) {
                call.reject("Download failed: " + e.getMessage());
            }
        }).start();
    }

    private boolean isValidApk(File apk) {
        return apk.exists() && apk.length() > 1024 * 100;
    }

    private void purgeCachedUpdates() {
        File cache = getContext().getCacheDir();
        File[] files = cache.listFiles();
        if (files == null) {
            return;
        }
        for (File f : files) {
            String name = f.getName();
            if (name.equals("fixit-update.apk")
                    || (name.startsWith(APK_PREFIX) && name.endsWith(".apk"))) {
                f.delete();
            }
        }
    }

    private void downloadApk(String urlString, File out) throws Exception {
        if (out.exists()) {
            out.delete();
        }

        HttpURLConnection conn = (HttpURLConnection) new URL(urlString).openConnection();
        conn.setInstanceFollowRedirects(true);
        conn.setConnectTimeout(30_000);
        conn.setReadTimeout(180_000);
        conn.setRequestProperty("User-Agent", "FixIt-OTA/1.0");
        conn.connect();

        int code = conn.getResponseCode();
        if (code < 200 || code >= 300) {
            throw new Exception("HTTP " + code);
        }

        try (InputStream in = conn.getInputStream(); FileOutputStream fos = new FileOutputStream(out)) {
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) != -1) {
                fos.write(buf, 0, n);
            }
            fos.flush();
        } finally {
            conn.disconnect();
        }

        if (!isValidApk(out)) {
            out.delete();
            throw new Exception("Downloaded file is too small or invalid");
        }
    }

    private void promptInstall(File apk) {
        Context ctx = getContext();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            if (!ctx.getPackageManager().canRequestPackageInstalls()) {
                Intent settings = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES);
                settings.setData(Uri.parse("package:" + ctx.getPackageName()));
                settings.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                getActivity().startActivity(settings);
                return;
            }
        }

        Uri uri = FileProvider.getUriForFile(ctx, ctx.getPackageName() + ".fileprovider", apk);
        Intent intent = new Intent(Intent.ACTION_VIEW);
        intent.setDataAndType(uri, "application/vnd.android.package-archive");
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
        getActivity().startActivity(intent);
    }
}