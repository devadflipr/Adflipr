import { useCallback, useState } from "react";
import {
  ERROR_CODES,
  messageForErrorCode,
  pickPrimaryErrorCode,
  resolveApiError,
} from "../utils/error-messages";

/**
 * Collect API failure codes from dashboard fetches and expose one user-facing banner message.
 */
export function useApiErrorBanner() {
  const [errorCodes, setErrorCodes] = useState([]);

  const registerFailure = useCallback((error) => {
    const { code } = resolveApiError(error);
    if (code === ERROR_CODES.UNKNOWN) {
      return;
    }
    setErrorCodes((prev) =>
      prev.includes(code) ? prev : [...prev, code],
    );
  }, []);

  const clearErrors = useCallback(() => {
    setErrorCodes([]);
  }, []);

  const primaryCode =
    errorCodes.length > 0
      ? pickPrimaryErrorCode(errorCodes)
      : null;

  const bannerMessage = primaryCode
    ? messageForErrorCode(primaryCode)
    : "";

  return {
    bannerMessage,
    registerFailure,
    clearErrors,
    hasError: Boolean(bannerMessage),
  };
}
