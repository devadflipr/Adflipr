<?php
/**
 * Same-origin Web Push service worker for WordPress storefronts.
 *
 * Served at /adflipr-push-sw.js with Service-Worker-Allowed: / so the worker
 * can cover the shop origin. Do not register a worker from adflipr.com.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! class_exists('Adflipr_Push_Sw')) {

  class Adflipr_Push_Sw
  {
    /** @var self|null */
    public static $_instance = null;

    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self();
      }

      return self::$_instance;
    }

    private function __construct()
    {
      add_action('parse_request', array($this, 'maybe_serve'), 0);
    }

    public function maybe_serve($wp)
    {
      $request = isset($wp->request) ? (string) $wp->request : '';
      $uri     = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
      $is_sw   = $request === 'adflipr-push-sw.js'
        || (bool) preg_match('#/adflipr-push-sw\\.js(\\?|$)#', $uri);
      if (! $is_sw) {
        return;
      }

      if (! function_exists('adflipr_ensure_store_uuid')) {
        require_once ADFLIPR_PLUGIN_DIR . '/includes/adflipr-install-telemetry.php';
      }

      nocache_headers();
      header('Content-Type: application/javascript; charset=utf-8');
      header('Service-Worker-Allowed: /');
      header('Cache-Control: public, max-age=300');
      echo $this->source();
      exit;
    }

    private function source()
    {
      $site_id  = function_exists('adflipr_ensure_store_uuid') ? (string) adflipr_ensure_store_uuid() : '';
      $api_base = function_exists('adflipr_api_url')
        ? adflipr_api_url('api/v1')
        : (defined('ADFLIPR_API_BASE_URL') ? rtrim((string) ADFLIPR_API_BASE_URL, '/') . '/api/v1' : '');

      return 'var ADFLIPR_PUSH_API = ' . wp_json_encode($api_base) . ";\n"
        . 'var ADFLIPR_PUSH_SITE = ' . wp_json_encode($site_id) . ";\n"
        . <<<'JS'
function adfliprPushDisplayUrl(url) {
  if (!url || url.charAt(0) === '/') {
    return '';
  }
  var absolute = /^https?:\/\//i.test(url) ? url : 'https://' + url;
  try {
    var parsed = new URL(absolute);
    var host = parsed.hostname.replace(/^www\./i, '');
    if (!host) {
      return '';
    }
    var path = parsed.pathname && parsed.pathname !== '/' ? parsed.pathname.replace(/\/+$/, '') : '';
    return host + path;
  } catch (e) {
    return '';
  }
}
function adfliprPushReport(kind, data) {
  if (!ADFLIPR_PUSH_API || !ADFLIPR_PUSH_SITE || !data || !data.subscriptionId) {
    return Promise.resolve();
  }
  return fetch(ADFLIPR_PUSH_API + '/web-push/public/' + encodeURIComponent(ADFLIPR_PUSH_SITE) + '/' + kind, {
    method: 'POST',
    credentials: 'omit',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      subscriptionId: data.subscriptionId,
      sourceId: data.sourceId || '',
      contactEmailId: data.contactEmailId || ''
    })
  }).catch(function () {});
}
self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {}
  var title = data.title || 'Notification';
  var body = data.body || '';
  var destination = adfliprPushDisplayUrl(data.clickUrl || '');
  if (destination) {
    body = body ? body + '\n' + destination : destination;
  }
  event.waitUntil(Promise.all([
    self.registration.showNotification(title, {
      body: body,
      icon: data.icon,
      image: data.image,
      tag: data.tag,
      data: data
    }),
    adfliprPushReport('views', data)
  ]));
});

self.addEventListener('notificationclick', function (event) {
  var data = event.notification.data || {};
  var closeKey = event.notification.tag || data.contactEmailId || '';
  self.__adfliprPushClicked = self.__adfliprPushClicked || {};
  self.__adfliprPushClicked[closeKey] = true;
  event.notification.close();
  var url = data.clickUrl || '/';
  event.waitUntil((async function () {
    await adfliprPushReport('clicks', data);
    var clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (var i = 0; i < clientsList.length; i++) {
      try {
        clientsList[i].postMessage({ type: 'adflipr-push-click', payload: data, reported: true });
      } catch (e) {}
    }
    if (self.clients.openWindow) {
      return self.clients.openWindow(url);
    }
  })());
});
self.addEventListener('notificationclose', function (event) {
  var data = event.notification.data || {};
  var closeKey = event.notification.tag || data.contactEmailId || '';
  self.__adfliprPushClicked = self.__adfliprPushClicked || {};
  if (self.__adfliprPushClicked[closeKey]) {
    delete self.__adfliprPushClicked[closeKey];
    return;
  }
  event.waitUntil(adfliprPushReport('closes', data));
});
JS;
    }
  }
}

if (class_exists('AdFlipr_Core') && class_exists('Adflipr_Push_Sw')) {
  AdFlipr_Core::register('push_sw', 'Adflipr_Push_Sw');
}
