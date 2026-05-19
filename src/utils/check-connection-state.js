import { apiClient } from "./api";
import { isIntegrationDisconnectedError } from "./error-messages";

/**
 * Confirms the stored WP token is still accepted by Adflipr (integration ACTIVE).
 * Triggers PHP soft_disconnect when the API returns integration_deleted / 401.
 */
async function verifyBackendAcceptsToken() {
  try {
    await apiClient("proxy/todo");
    return true;
  } catch (error) {
    if (isIntegrationDisconnectedError(error)) {
      return false;
    }
    // Network or transient errors: keep local session; dashboard may show its own banner.
    return true;
  }
}

/**
 * @returns {Promise<boolean>}
 */
export async function checkConnectionState() {
  try {
    const data = await apiClient("login-token", "GET");
    if (!data?.loginToken) {
      return false;
    }
    return verifyBackendAcceptsToken();
  } catch {
    return false;
  }
}
