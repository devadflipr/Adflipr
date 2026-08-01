const WP_TODO_DISMISSED_KEY = "adflipr_wp_todo_dismissed";

export function isWpTodoDismissed() {
  try {
    return window.localStorage.getItem(WP_TODO_DISMISSED_KEY) === "1";
  } catch {
    return false;
  }
}

export function setWpTodoDismissed() {
  try {
    window.localStorage.setItem(WP_TODO_DISMISSED_KEY, "1");
  } catch {
    // ignore quota / private mode
  }
}

export function clearWpTodoDismissed() {
  try {
    window.localStorage.removeItem(WP_TODO_DISMISSED_KEY);
  } catch {
    // ignore
  }
}

/** API todo flags are booleans; avoid truthy strings like "false". */
export function isTodoFlag(value) {
  return value === true;
}
