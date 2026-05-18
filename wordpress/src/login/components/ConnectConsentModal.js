import { Button, Dialog } from "@mui/material";
import React from "react";
import { ReactComponent as IconCross } from "../../assets/icons/icon-cross.svg";
import wooLogo from "../../assets/images/Woo_logo_color.png";
import FormError from "../../components/FormError";
import Progress from "../../components/Progress";

const PERMISSIONS = [
  "Sync products and inventory",
  "Sync customer and contact information",
  "Track cart and checkout activity",
  "Enable abandoned cart recovery features",
  "Create secure integration webhooks",
];

const ConnectConsentModal = ({
  open,
  onClose,
  onApprove,
  error,
  isLoading,
}) => {
  return (
    <Dialog
      open={Boolean(open)}
      onClose={onClose}
      className="adflipr-login-dialog"
      PaperProps={{ className: "adflipr-login-dialog__paper" }}
      maxWidth={false}
    >
      <button
        type="button"
        className="adflipr-login-dialog__close"
        onClick={onClose}
        aria-label="Close"
        disabled={isLoading}
      >
        <IconCross className="icon24" />
      </button>
      <div className="adflipr-login-dialog__content adflipr-consent-dialog__content">
        <img
          src={wooLogo}
          alt="WooCommerce"
          className="adflipr-consent-dialog__logo"
        />
        <h2 className="adflipr-login-dialog__title adflipr-consent-dialog__title">
          Connect your WooCommerce store with Adflipr
        </h2>
        <p className="adflipr-login-dialog__subtitle adflipr-consent-dialog__subtitle">
          Adflipr requires your permission before syncing your store data.
        </p>

        <FormError
          message={error}
          className="adflipr-login-dialog__error-banner"
        />

        <ul className="adflipr-consent-dialog__permissions">
          {PERMISSIONS.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>

        <p className="adflipr-consent-dialog__security-note">
          Adflipr only accesses the data required for recovery, analytics, and
          customer engagement features.
        </p>

        <p className="adflipr-consent-dialog__footer-note">
          Approving will securely connect this WooCommerce store with your
          Adflipr account.
        </p>

        <div className="adflipr-login-dialog__actions adflipr-consent-dialog__actions">
          <Button
            type="button"
            className="btn adflipr-consent-dialog__cancel"
            variant="outlined"
            disableRipple
            disableElevation
            onClick={onClose}
            disabled={isLoading}
          >
            Cancel
          </Button>
          <Button
            type="button"
            className="btn btn-primary adflipr-login-dialog__submit adflipr-consent-dialog__approve"
            variant="contained"
            disableRipple
            disableElevation
            onClick={onApprove}
            disabled={isLoading}
          >
            {isLoading ? <Progress button={true} /> : null}
            <span
              className={
                isLoading ? "adflipr-login-dialog__submit-label--hidden" : ""
              }
            >
              Approve
            </span>
          </Button>
        </div>
      </div>
    </Dialog>
  );
};

export default ConnectConsentModal;
