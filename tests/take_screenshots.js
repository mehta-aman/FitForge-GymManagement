const fs = require('fs');
const path = require('path');

const ARTIFACTS_DIR = '/Users/amanmehta/.gemini/antigravity-cli/brain/520ab020-774b-4048-9c22-854803288cb9';

async function sendCdp(ws, method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = Math.floor(Math.random() * 1000000);
    const handler = (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.id === id) {
          ws.removeEventListener('message', handler);
          if (msg.error) reject(msg.error);
          else resolve(msg.result);
        }
      } catch (e) {
        // ignore parse error of other events
      }
    };
    ws.addEventListener('message', handler);
    ws.send(JSON.stringify({ id, method, params }));
  });
}

async function capture(url, outFileName, width = 390, height = 844, isMobile = true) {
  const versionRes = await fetch('http://127.0.0.1:9226/json/new?' + encodeURIComponent(url), { method: 'PUT' });
  const target = await versionRes.json();
  const ws = new WebSocket(target.webSocketDebuggerUrl);

  await new Promise((resolve) => ws.addEventListener('open', resolve, { once: true }));

  await sendCdp(ws, 'Page.enable');
  await sendCdp(ws, 'Emulation.setDeviceMetricsOverride', {
    width,
    height,
    deviceScaleFactor: 2,
    mobile: isMobile
  });

  await sendCdp(ws, 'Page.navigate', { url });
  // Wait 1.5 seconds for CSS animations, fonts, and layout
  await new Promise(r => setTimeout(r, 1500));

  const screenshot = await sendCdp(ws, 'Page.captureScreenshot', {
    format: 'png',
    captureBeyondViewport: false
  });

  const dest = path.join(ARTIFACTS_DIR, outFileName);
  fs.writeFileSync(dest, Buffer.from(screenshot.data, 'base64'));
  console.log(`Saved screenshot: ${outFileName}`);

  await sendCdp(ws, 'Target.closeTarget', { targetId: target.id });
  ws.close();
}

async function main() {
  console.log('Capturing screenshots for Unified Authentication & Registration...');
  
  // 1. Mobile Member Login
  await capture('http://127.0.0.1:8080/login.php?portal=member', 'unified_login_member_mobile.png', 390, 844, true);

  // 2. Mobile Staff Login
  await capture('http://127.0.0.1:8080/login.php?portal=staff', 'unified_login_staff_mobile.png', 390, 844, true);

  // 3. Mobile Member Registration
  await capture('http://127.0.0.1:8080/registration.php?portal=member', 'unified_registration_member_mobile.png', 390, 844, true);

  // 4. Mobile Staff Registration
  await capture('http://127.0.0.1:8080/registration.php?portal=staff', 'unified_registration_staff_mobile.png', 390, 844, true);

  // 5. Desktop Unified Login
  await capture('http://127.0.0.1:8080/login.php', 'unified_login_desktop.png', 1280, 800, false);

  console.log('All screenshots captured successfully!');
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
