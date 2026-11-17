// FAJTech Chat inbox service worker: shows push notifications for new chat messages.
// Pushes carry no data; the details are fetched from the chat API using the signed-in session.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let n = { title: 'FAJTech Chat', body: 'You have a new chat message.', url: '/agent/', tag: 'fajchat' };
    try {
      const r = await fetch('/api/chat.php?action=push_preview', { credentials: 'include', cache: 'no-store' });
      const j = await r.json();
      if (j && j.ok && j.title) n = j;
      else if (r.status === 401) n.body = 'New chat message. Open the inbox and sign in to reply.';
    } catch (e) { /* show the generic notification */ }
    await self.registration.showNotification(n.title, {
      body: n.body, tag: n.tag, renotify: true, data: { url: n.url },
      icon: '/agent/icon-192.png', badge: '/agent/icon-192.png',
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || '/agent/', self.location.origin).href;
  event.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) {
      if (w.url.startsWith(self.location.origin + '/agent/')) { await w.focus(); w.postMessage({ open: url }); return; }
    }
    await self.clients.openWindow(url);
  })());
});
