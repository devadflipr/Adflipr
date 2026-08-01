import React from "react";

const contactActivityLinkStyle = {
  color: "#565ADD",
  textDecoration: "none",
  cursor: "pointer",
  background: "none",
  border: "none",
  font: "inherit",
  padding: 0,
};

/**
 * Activity line text for EMAIL_* types (contact name embedded for link rendering).
 */
export function activitySentance(name, action) {
  if (!action || typeof action !== "string") {
    return "Invalid input";
  }

  const safeName = typeof name === "string" ? name.trim() : "";
  const formattedAction = action
    .split("_")
    .slice(1)
    .join(" ")
    .toLowerCase();

  if (!safeName) {
    return formattedAction ? `${formattedAction} the email.` : "Invalid input";
  }

  if (action === "EMAIL_SENT") {
    return `send to ${safeName}`;
  }

  return `${safeName} ${formattedAction} the email.`;
}

/** Renders activity text with an optional clickable contact name. */
export function renderEmailActivityMessage(
  text,
  contactName,
  contactId,
  onContactClick,
) {
  const trimmedName = contactName?.trim() ?? "";
  if (!trimmedName || !contactId || !text.includes(trimmedName)) {
    return text;
  }

  const parts = text.split(trimmedName);
  return (
    <>
      {parts.map((part, index) => (
        <React.Fragment key={`${index}-${part.slice(0, 8)}`}>
          {part}
          {index < parts.length - 1 ? (
            <button
              type="button"
              style={contactActivityLinkStyle}
              onClick={() => onContactClick(contactId)}
            >
              {trimmedName}
            </button>
          ) : null}
        </React.Fragment>
      ))}
    </>
  );
}
