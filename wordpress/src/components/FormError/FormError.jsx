import React from "react";

/**
 * Adflipr error banner (matches main app `.error-info` pattern).
 */
const FormError = ({ message, className = "" }) => {
  if (!message || String(message).trim() === "") {
    return null;
  }

  return (
    <div className={`error-info ${className}`.trim()} role="alert">
      <span>{message}</span>
    </div>
  );
};

export default FormError;
