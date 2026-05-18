// REST client for AdFlipr wp-json routes (requires admin cookie + WP REST nonce).

import { resolveApiError } from "./error-messages";

const ADFLIPR_TIMEOUT_MS = 30_000;

function createApiError(input) {
  const { code, message } = resolveApiError(input);
  const err = new Error(message);
  err.adfliprCode = code;
  return err;
}

function getWpApiFetch() {
  if (
    typeof window !== "undefined" &&
    window.wp &&
    typeof window.wp.apiFetch === "function"
  ) {
    return window.wp.apiFetch;
  }
  return null;
}

function getRestNonce() {
  if (
    typeof window !== "undefined" &&
    window.adfliprWp &&
    window.adfliprWp.restNonce
  ) {
    return window.adfliprWp.restNonce;
  }
  return "";
}

function getRestBaseUrl() {
  if (
    typeof window !== "undefined" &&
    window.adfliprWp &&
    window.adfliprWp.restUrl
  ) {
    return String(window.adfliprWp.restUrl).replace(/\/?$/, "/");
  }
  return `${window.location.origin}/wp-json/adflipr/v1/`;
}

function buildPathSegments(path, method, params) {
  let p = `${path.replace(/^\//, "")}`;
  if (method === "GET" && params && Object.keys(params).length > 0) {
    p += `?${new URLSearchParams(params)}`;
  }
  return `/adflipr/v1/${p}`;
}

/** Parse proxy-style JSON payloads that wrap upstream body as a string. */
function normalizeResponseData(data) {
  if (data && typeof data.body === "string") {
    try {
      return JSON.parse(data.body);
    } catch {
      return data;
    }
  }
  return data;
}

/**
 * Prefer core wp.apiFetch (correct rest_url root + nonce for cookie auth).
 * Fallback: absolute URL from localized restUrl (subdir installs) + fetch.
 */
export const apiClient = (
  path,
  method = "GET",
  params = null,
  body = null,
) => {
  const wpApiFetch = getWpApiFetch();
  const requestPath = buildPathSegments(path, method, params);

  if (wpApiFetch) {
    const options = {
      path: requestPath,
      method: method.toUpperCase(),
      parse: true,
    };
    if (body && options.method !== "GET") {
      options.data = body;
    }

    let timeoutId;
    const timeout = new Promise((_, reject) => {
      timeoutId = setTimeout(
        () => reject(createApiError("Request timed out")),
        ADFLIPR_TIMEOUT_MS,
      );
    });

    return Promise.race([
      wpApiFetch(options),
      timeout,
    ])
      .finally(() => {
        clearTimeout(timeoutId);
      })
      .then((data) => normalizeResponseData(data))
      .catch((error) => {
        console.log("AdFlipr apiFetch:", requestPath, error);
        if (error?.adfliprCode) {
          throw error;
        }
        const payload =
          error?.data &&
          typeof error.data === "object" &&
          error.data.status === false
            ? error.data
            : error;
        throw createApiError(payload);
      });
  }

  const nonce = getRestNonce();
  const headers = {
    "Content-Type": "application/json",
  };
  if (nonce) {
    headers["X-WP-Nonce"] = nonce;
  }

  const base = getRestBaseUrl();
  const url =
    `${base}${path.replace(/^\//, "")}${method === "GET" && params ? `?${new URLSearchParams(params)}` : ""}`;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => {
    controller.abort();
  }, ADFLIPR_TIMEOUT_MS);

  return fetch(url, {
    method,
    headers,
    body: body && method !== "GET" ? JSON.stringify(body) : null,
    credentials: "same-origin",
    signal: controller.signal,
  })
    .then(async (response) => {
      if (!response.ok) {
        let detail = `${response.status} ${response.statusText}`;
        try {
          const text = await response.clone().json();
          if (text.code) {
            detail += ` (${String(text.code)})`;
          }
          if (text.message) {
            detail += `: ${String(text.message)}`;
          }
        } catch {
          //
        }
        throw createApiError(detail.trim());
      }
      return response.json();
    })
    .then((data) => normalizeResponseData(data))
    .catch((error) => {
      console.log("Error fetching:", url, error);
      if (error?.adfliprCode) {
        throw error;
      }
      throw createApiError(error);
    })
    .finally(() => {
      clearTimeout(timeoutId);
    });
};
