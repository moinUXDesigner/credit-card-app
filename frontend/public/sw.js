const CACHE = 'ccapp-shell-v1'
self.addEventListener('install', event => { event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(['/','/index.html']))); self.skipWaiting() })
self.addEventListener('activate', event => { event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k=>k.startsWith('ccapp-shell-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())) })
self.addEventListener('fetch', event => {
 const request=event.request, url=new URL(request.url)
 if(request.method!=='GET'||url.origin!==self.location.origin||url.pathname.startsWith('/api/')||request.headers.has('Authorization')) return
 if(request.mode==='navigate') {
  event.respondWith(fetch(request).then(async response=>{if(response.ok){const cache=await caches.open(CACHE);await cache.put('/index.html',response.clone())}return response}).catch(()=>caches.match('/index.html')));return
 }
 if(['script','style','font','image'].includes(request.destination))event.respondWith(fetch(request).then(async response=>{if(response.ok){const cache=await caches.open(CACHE);await cache.put(request,response.clone())}return response}).catch(()=>caches.match(request,{ignoreVary:true})))
})
self.addEventListener('message',event=>{
 if(event.data?.type!=='CACHE_SHELL')return
 const urls=event.data.urls.filter(value=>{const url=new URL(value,self.location.origin);return url.origin===self.location.origin&&!url.pathname.startsWith('/api/')})
 event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(urls)).then(()=>event.ports[0]?.postMessage({ok:true})).catch(()=>event.ports[0]?.postMessage({ok:false})))
})
