/**
 * AdFlipr storefront attribution + checkout cart recovery.
 *
 * Integration test (manual):
 * 1. Open a product URL with UTMs, e.g.
 *    /product/example?utm_source=adflipr&utm_medium=email&utm_campaign=utm_v2-969
 * 2. DevTools → Application → Cookies: confirm utm_source, utm_medium, utm_campaign exist (30-day lifetime).
 * 3. Add product to cart (URL may lose query params — cookies must remain).
 * 4. Open checkout; console should log "[AdFlipr Attribution] Loaded cookies: …".
 * 5. Place order.
 * 6. Confirm checkout_purchased webhook payload includes tracking.utm_source = "adflipr".
 * 7. Confirm Adflipr backend classifies the transaction as txnSource ADFLIPR_UTM (not DIRECT).
 */
(function () {
  "use strict";

  var LOG_PREFIX = "[AdFlipr Attribution]";
  var UTM_KEYS = [
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
  ];
  var COOKIE_MAX_AGE_SECONDS = 30 * 24 * 60 * 60;
  var WEB_PUSH_ENDPOINT_KEY = "adflipr_web_push_endpoint";
  var WEB_PUSH_SUBSCRIPTION_ID_KEY = "adflipr_web_push_subscription_id";

  function readWebPushIdentity() {
    var identity = {};
    try {
      var endpoint = sessionStorage.getItem(WEB_PUSH_ENDPOINT_KEY);
      var subscriptionId = sessionStorage.getItem(WEB_PUSH_SUBSCRIPTION_ID_KEY);
      if (endpoint) {
        identity.web_push_endpoint = endpoint;
      }
      if (subscriptionId) {
        identity.web_push_subscription_id = subscriptionId;
      }
    } catch (e) {}
    return identity;
  }

  function setUtmCookie(name, value) {
    document.cookie =
      name +
      "=" +
      encodeURIComponent(value) +
      ";path=/;SameSite=Lax;max-age=" +
      COOKIE_MAX_AGE_SECONDS;
  }

  function readUtmCookies() {
    var cookies = {};
    var parts = document.cookie ? document.cookie.split(";") : [];

    parts.forEach(function (part) {
      var segment = part.trim();
      if (!segment) {
        return;
      }
      var eq = segment.indexOf("=");
      if (eq === -1) {
        return;
      }
      var key = segment.slice(0, eq).trim();
      if (UTM_KEYS.indexOf(key) === -1) {
        return;
      }
      cookies[key] = decodeURIComponent(segment.slice(eq + 1));
    });

    return cookies;
  }

  function captureUtmFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var captured = {};
    var hasAny = false;

    UTM_KEYS.forEach(function (key) {
      var value = params.get(key);
      if (value && String(value).trim() !== "") {
        captured[key] = String(value).trim();
        hasAny = true;
      }
    });

    if (!hasAny) {
      return captured;
    }

    Object.keys(captured).forEach(function (key) {
      setUtmCookie(key, captured[key]);
    });

    console.log(LOG_PREFIX);
    console.log("Captured UTM:");
    console.log("source=" + (captured.utm_source || ""));
    console.log("medium=" + (captured.utm_medium || ""));
    console.log("campaign=" + (captured.utm_campaign || ""));
    if (captured.utm_term) {
      console.log("term=" + captured.utm_term);
    }
    if (captured.utm_content) {
      console.log("content=" + captured.utm_content);
    }

    return captured;
  }

  function logLoadedCookiesOnCheckout() {
    var cookies = readUtmCookies();

    console.log(LOG_PREFIX);
    console.log("Loaded cookies:");
    if (Object.keys(cookies).length === 0) {
      console.log("(none)");
      return;
    }

    UTM_KEYS.forEach(function (key) {
      if (cookies[key]) {
        console.log(key + "=" + cookies[key]);
      }
    });
  }

  captureUtmFromUrl();
  window.addEventListener("popstate", captureUtmFromUrl);

  var isCheckout =
    typeof adflipr_vars !== "undefined" && adflipr_vars.is_checkout === true;

  if (isCheckout) {
    logLoadedCookiesOnCheckout();
  }

  if (!isCheckout || typeof jQuery === "undefined") {
    return;
  }

  jQuery(document).ready(function ($) {
    function adflipr_sendEmail(adflipr_email) {
      if (!adflipr_email || typeof adflipr_vars === "undefined") {
        return;
      }
      setTimeout(function () {
        var payload = {
          action: "adflipr_save_cart",
          nonce: adflipr_vars.nonce,
          security: adflipr_vars.security,
          email: adflipr_email,
        };
        var pushIdentity = readWebPushIdentity();
        if (pushIdentity.web_push_endpoint) {
          payload.web_push_endpoint = pushIdentity.web_push_endpoint;
        }
        if (pushIdentity.web_push_subscription_id) {
          payload.web_push_subscription_id = pushIdentity.web_push_subscription_id;
        }
        $.post(adflipr_vars.ajax_url, payload);
      }, 3000);
    }

    function adflipr_isValidEmail() {
      return (
        $(
          ".wc-block-components-text-input.has-error, #billing_email_field.woocommerce-invalid",
        ).length === 0
      );
    }

    function adflipr_checkEmailOnBlur() {
      $(document).on("blur", "#billing_email, input#email", function () {
        var adflipr_emailValue = $(this).val();
        setTimeout(function () {
          if (adflipr_emailValue && adflipr_isValidEmail()) {
            adflipr_sendEmail(adflipr_emailValue);
          }
        }, 100);
      });
    }

    function adflipr_checkAutofilledEmail() {
      setTimeout(function () {
        var adflipr_emailClassic = $("#billing_email").val();
        var adflipr_emailBlock = $("input#email").val();

        if (adflipr_emailClassic && adflipr_isValidEmail()) {
          adflipr_sendEmail(adflipr_emailClassic);
        }

        if (adflipr_emailBlock && adflipr_isValidEmail()) {
          adflipr_sendEmail(adflipr_emailBlock);
        }
      }, 3000);
    }

    adflipr_checkEmailOnBlur();
    adflipr_checkAutofilledEmail();
  });
})();
