/**
 * User-facing error messages for the WordPress plugin admin UI.
 * Mirrors includes/adflipr-user-messages.php (English literals; WP PHP uses __()).
 */

export const ERROR_CODES = {
  INVALID_CREDENTIALS: "INVALID_CREDENTIALS",
  SERVER_ERROR: "SERVER_ERROR",
  NETWORK_ERROR: "NETWORK_ERROR",
  WC_SETUP_ERROR: "WC_SETUP_ERROR",
  ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE: "ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE",
  STORE_ALREADY_ON_ANOTHER_ACCOUNT: "STORE_ALREADY_ON_ANOTHER_ACCOUNT",
  ACCOUNT_ALREADY_HAS_STORE_INTEGRATION: "ACCOUNT_ALREADY_HAS_STORE_INTEGRATION",
  SESSION_EXPIRED: "SESSION_EXPIRED",
  RATE_LIMITED: "RATE_LIMITED",
  INVALID_REQUEST: "INVALID_REQUEST",
  UNKNOWN: "UNKNOWN",
};

const USER_MESSAGES = {
  [ERROR_CODES.INVALID_CREDENTIALS]:
    "The email or password you entered is incorrect.",
  [ERROR_CODES.SERVER_ERROR]:
    "Something went wrong on our end. Please try again in a few minutes.",
  [ERROR_CODES.NETWORK_ERROR]:
    "Could not reach Adflipr. Check your internet connection and try again.",
  [ERROR_CODES.WC_SETUP_ERROR]:
    "We could not connect WooCommerce on this site. Ensure WooCommerce is active and try again.",
  [ERROR_CODES.ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE]:
    "This Adflipr account is already connected to another WooCommerce store. Disconnect it in Adflipr (App Integrations) or on the original WordPress site, then try again here.",
  [ERROR_CODES.STORE_ALREADY_ON_ANOTHER_ACCOUNT]:
    "This WooCommerce store is already connected to another Adflipr account. Sign in with that account or disconnect the store there first.",
  [ERROR_CODES.ACCOUNT_ALREADY_HAS_STORE_INTEGRATION]:
    "This Adflipr account already has a store connected (for example Shopify). Disconnect it in Adflipr App Integrations before connecting WooCommerce.",
  [ERROR_CODES.SESSION_EXPIRED]:
    "Your session expired. Refresh this page and try again.",
  [ERROR_CODES.RATE_LIMITED]:
    "Too many attempts. Please wait a moment and try again.",
  [ERROR_CODES.INVALID_REQUEST]:
    "Please check your details and try again.",
  [ERROR_CODES.UNKNOWN]: "Something went wrong. Please try again.",
};

const TECHNICAL_PREFIXES = [
  /^token could not be saved:\s*/i,
  /^adflipr login failed\s*\(http\s*\d+\):\s*/i,
  /^authentication failed:\s*/i,
];

function stripTechnicalPrefixes(text) {
  let out = text.trim();
  for (const pattern of TECHNICAL_PREFIXES) {
    out = out.replace(pattern, "");
  }
  return out.trim();
}

function normalizeDetail(text) {
  return stripTechnicalPrefixes(String(text || "")).toLowerCase();
}

/**
 * @param {string} code
 * @returns {string}
 */
export function messageForErrorCode(code) {
  if (code && USER_MESSAGES[code]) {
    return USER_MESSAGES[code];
  }
  return USER_MESSAGES[ERROR_CODES.UNKNOWN];
}

/**
 * Classify a raw technical string into an error code (fallback when API omits `code`).
 * @param {string} detail
 * @param {number} [httpStatus]
 * @returns {string}
 */
export function classifyErrorDetail(detail, httpStatus = 0) {
  const d = normalizeDetail(detail);
  const http = Number(httpStatus) || 0;

  const httpMatch = d.match(/\(http\s*(\d{3})\)/i) || d.match(/\b(\d{3})\s*:/);
  const status = http || (httpMatch ? parseInt(httpMatch[1], 10) : 0);

  if (
    status === 401 ||
    status === 403 ||
    d.includes("invalid") ||
    d.includes("incorrect") ||
    d.includes("unauthorized") ||
    d.includes("authentication failed") ||
    d.includes("bad credentials") ||
    d.includes("wrong password")
  ) {
    return ERROR_CODES.INVALID_CREDENTIALS;
  }

  if (
    status === 429 ||
    d.includes("too many") ||
    d.includes("rate limit")
  ) {
    return ERROR_CODES.RATE_LIMITED;
  }

  if (d.includes("already has a connected woocommerce store")) {
    return ERROR_CODES.ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE;
  }

  if (d.includes("already connected to another account")) {
    return ERROR_CODES.STORE_ALREADY_ON_ANOTHER_ACCOUNT;
  }

  if (d.includes("already has a connected store")) {
    return ERROR_CODES.ACCOUNT_ALREADY_HAS_STORE_INTEGRATION;
  }

  if (
    d.includes("api key") ||
    d.includes("super admin") ||
    d.includes("could not prepare woocommerce")
  ) {
    return ERROR_CODES.WC_SETUP_ERROR;
  }

  if (status >= 502 || d.includes("service unavailable")) {
    return ERROR_CODES.SERVER_ERROR;
  }

  if (
    status === 400 ||
    status === 422 ||
    d.includes("invalid request")
  ) {
    return ERROR_CODES.INVALID_REQUEST;
  }

  if (
    d.includes("timed out") ||
    d.includes("timeout") ||
    d.includes("could not resolve") ||
    d.includes("connection refused") ||
    d.includes("network") ||
    d.includes("failed to fetch")
  ) {
    return ERROR_CODES.NETWORK_ERROR;
  }

  if (
    d.includes("nonce") ||
    d.includes("not connected") ||
    d.includes("rest_cookie") ||
    d.includes("session expired")
  ) {
    return ERROR_CODES.SESSION_EXPIRED;
  }

  return ERROR_CODES.UNKNOWN;
}

/**
 * @param {{ status?: boolean, error_code?: string, message?: string, code?: string } | Error | string | null | undefined} input
 * @returns {{ code: string, message: string }}
 */
export function resolveUserError(input) {
  if (input == null) {
    return {
      code: ERROR_CODES.UNKNOWN,
      message: messageForErrorCode(ERROR_CODES.UNKNOWN),
    };
  }

  if (typeof input === "string") {
    const code = classifyErrorDetail(input);
    return { code, message: messageForErrorCode(code) };
  }

  if (input instanceof Error) {
    if (typeof input.adfliprCode === "string" && USER_MESSAGES[input.adfliprCode]) {
      const msg =
        typeof input.message === "string" && input.message.trim() !== ""
          ? input.message.trim()
          : messageForErrorCode(input.adfliprCode);
      return { code: input.adfliprCode, message: msg };
    }
    const code = classifyErrorDetail(input.message);
    const trimmed =
      typeof input.message === "string" ? input.message.trim() : "";
    if (trimmed !== "" && code === ERROR_CODES.UNKNOWN) {
      return { code, message: trimmed };
    }
    return { code, message: messageForErrorCode(code) };
  }

  if (typeof input === "object") {
    const nested =
      input.data && typeof input.data === "object" && input.data.status === false
        ? input.data
        : null;

    const apiCode =
      typeof input.code === "string" && USER_MESSAGES[input.code]
        ? input.code
        : typeof input.error_code === "string" && USER_MESSAGES[input.error_code]
          ? input.error_code
          : nested &&
              typeof nested.error_code === "string" &&
              USER_MESSAGES[nested.error_code]
            ? nested.error_code
            : "";
    if (apiCode) {
      const msgSource = nested || input;
      return {
        code: apiCode,
        message:
          typeof msgSource.message === "string" && msgSource.message.trim() !== ""
            ? msgSource.message.trim()
            : messageForErrorCode(apiCode),
      };
    }

    const raw =
      typeof input.message === "string"
        ? input.message
        : nested && typeof nested.message === "string"
          ? nested.message
          : "";
    const trimmed = typeof raw === "string" ? raw.trim() : "";
    const code = classifyErrorDetail(trimmed);
    if (trimmed !== "") {
      return { code, message: trimmed };
    }
    return { code, message: messageForErrorCode(code) };
  }

  return {
    code: ERROR_CODES.UNKNOWN,
    message: messageForErrorCode(ERROR_CODES.UNKNOWN),
  };
}

/** Login form failures. */
export function resolveLoginError(input) {
  return resolveUserError(input);
}

/**
 * True when Adflipr rejected the WP token or integration is inactive (disconnect from app).
 * @param {unknown} error
 */
export function isIntegrationDisconnectedError(error) {
  if (error == null) {
    return false;
  }
  if (typeof error === "object" && !(error instanceof Error)) {
    if (error.code === "integration_deleted" || error.integration_deleted === true) {
      return true;
    }
    if (
      error.data &&
      typeof error.data === "object" &&
      (error.data.code === "integration_deleted" ||
        error.data.integration_deleted === true)
    ) {
      return true;
    }
  }
  if (error instanceof Error) {
    if (error.adfliprCode === ERROR_CODES.SESSION_EXPIRED) {
      return true;
    }
    const msg = error.message.toLowerCase();
    if (
      msg.includes("integration_deleted") ||
      msg.includes("not connected to adflipr") ||
      msg.includes("integration is disconnected")
    ) {
      return true;
    }
    if (error.data && typeof error.data === "object") {
      return isIntegrationDisconnectedError(error.data);
    }
  }
  return false;
}

/**
 * Message for login-token failures. Prefers `message` from WP REST / API payload.
 * @param {unknown} input
 * @returns {string}
 */
export function getLoginFailureMessage(input) {
  if (input != null && typeof input === "object" && !(input instanceof Error)) {
    const payload =
      input.data &&
      typeof input.data === "object" &&
      input.data.status === false
        ? input.data
        : input;
    if (
      payload.status === false &&
      typeof payload.message === "string" &&
      payload.message.trim() !== ""
    ) {
      return payload.message.trim();
    }
  }
  return resolveLoginError(input).message;
}

/** Dashboard / proxy API failures. */
export function resolveApiError(input) {
  return resolveUserError(input);
}

/**
 * Pick the most severe error when multiple requests fail (session > network > server).
 * @param {string[]} codes
 */
export function pickPrimaryErrorCode(codes) {
  const priority = [
    ERROR_CODES.SESSION_EXPIRED,
    ERROR_CODES.INVALID_CREDENTIALS,
    ERROR_CODES.ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE,
    ERROR_CODES.STORE_ALREADY_ON_ANOTHER_ACCOUNT,
    ERROR_CODES.ACCOUNT_ALREADY_HAS_STORE_INTEGRATION,
    ERROR_CODES.WC_SETUP_ERROR,
    ERROR_CODES.NETWORK_ERROR,
    ERROR_CODES.SERVER_ERROR,
    ERROR_CODES.RATE_LIMITED,
    ERROR_CODES.INVALID_REQUEST,
    ERROR_CODES.UNKNOWN,
  ];
  for (const code of priority) {
    if (codes.includes(code)) {
      return code;
    }
  }
  return ERROR_CODES.UNKNOWN;
}
