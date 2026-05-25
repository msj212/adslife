<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Signing in to AdsLife...</title>
  <style>
    body { font-family: sans-serif; display: flex; align-items: center; justify-content: center;
           height: 100vh; margin: 0; background: #fff; flex-direction: column; }
    p { color: #555; font-size: 16px; }
    .spinner { width: 36px; height: 36px; border: 4px solid #f3f3f3;
               border-top-color: #FF6200; border-radius: 50%;
               animation: spin 0.8s linear infinite; margin-bottom: 16px; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="spinner"></div>
  <p>Completing sign in…</p>
  <script>
    (async function () {
      // Parse URL fragment (Google puts access_token here for implicit flow)
      const hash = window.location.hash.substring(1);
      const frag = {};
      hash.split('&').forEach(function(p) {
        const i = p.indexOf('=');
        if (i > 0) frag[p.slice(0, i)] = decodeURIComponent(p.slice(i + 1));
      });

      // Parse state to get the app's deep-link URI
      let appUri = 'com.adslife.app://auth';
      try {
        const stateData = JSON.parse(atob(frag['state'] || ''));
        if (stateData.app_uri) appUri = stateData.app_uri;
      } catch (e) {}

      // Separator: exp:// URIs use ?param=, others too
      function buildReturnUrl(base, params) {
        const sep = base.includes('?') ? '&' : '?';
        const qs  = Object.entries(params).map(([k, v]) => k + '=' + encodeURIComponent(v)).join('&');
        return base + sep + qs;
      }

      const accessToken = frag['access_token'];
      if (!accessToken) {
        window.location.href = buildReturnUrl(appUri, { error: 'no_token' });
        return;
      }

      const abort1 = new AbortController();
      const t1 = setTimeout(() => abort1.abort(), 8000);
      try {
        // Fetch userinfo from browser — avoids slow server-to-server call
        const uiRes = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
          headers: { 'Authorization': 'Bearer ' + accessToken },
          signal: abort1.signal
        });
        clearTimeout(t1);
        const userinfo = await uiRes.json();

        if (!userinfo.sub) {
          window.location.href = buildReturnUrl(appUri, { error: 'invalid_token' });
          return;
        }

        const abort2 = new AbortController();
        const t2 = setTimeout(() => abort2.abort(), 8000);

        const res = await fetch('https://adslife.stss.in/backend/api/auth/google.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id_token: accessToken, userinfo: userinfo }),
          signal: abort2.signal
        });
        clearTimeout(t2);
        const data = await res.json();

        if (data.success) {
          window.location.href = buildReturnUrl(appUri, {
            token: data.data.token,
            user:  JSON.stringify(data.data.user)
          });
        } else {
          window.location.href = buildReturnUrl(appUri, { error: data.error || 'signin_failed' });
        }
      } catch (e) {
        clearTimeout(t1);
        window.location.href = buildReturnUrl(appUri, {
          error: e.name === 'AbortError' ? 'timeout' : 'network_error'
        });
      }
    })();
  </script>
</body>
</html>
