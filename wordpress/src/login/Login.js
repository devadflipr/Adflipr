import { Button, Dialog } from "@mui/material";
import React, { useState } from "react";
import * as zod from "zod";
import { loginSchema } from "../api/validations";
import { ReactComponent as IconCross } from "../assets/icons/icon-cross.svg";
import ImageNoSearch from "../assets/images/no-search-results.png";
import FormError from "../components/FormError";
import Progress from "../components/Progress";
import AnalyticsCard from "../dashboard/components/AnalyticsCards";
import PerformanceMetrics from "../dashboard/components/PerformanceMetrics";
import QuickLinks from "../dashboard/components/QuickLinks";
import RecentCard from "../dashboard/components/RecentCards";
import { apiClient } from "../utils";
import { resolveLoginError } from "../utils/error-messages";
import ConnectConsentModal from "./components/ConnectConsentModal";
import TodoListPreview from "./components/TodoListPreview";

const Login = ({ setIsLoggedIn, loginOpen, onLoginOpen, onLoginClose }) => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);

  const [consentOpen, setConsentOpen] = useState(false);
  const [consentError, setConsentError] = useState("");
  const [isApproving, setIsApproving] = useState(false);
  const [pendingCredentials, setPendingCredentials] = useState(null);

  const resetConsentFlow = () => {
    setConsentOpen(false);
    setConsentError("");
    setIsApproving(false);
    setPendingCredentials(null);
  };

  const handleConsentDismiss = () => {
    resetConsentFlow();
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setIsLoading(true);

    try {
      await loginSchema.parseAsync({ email, password });
      setErrors({});

      const response = await apiClient("login-token", "POST", null, {
        email,
        password,
        integration: false,
      });

      if (!response.status) {
        const { message } = resolveLoginError(response);
        throw new Error(message);
      }

      setPendingCredentials({ email, password });
      onLoginClose?.();
      setConsentOpen(true);
    } catch (error) {
      if (error instanceof zod.ZodError) {
        const newErrors = {};
        error.issues.forEach((issue) => {
          newErrors[issue.path[0]] = issue.message;
        });
        setErrors(newErrors);
        return;
      }

      console.error("Login error:", error);
      const { message } = resolveLoginError(error);
      setError(message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleApproveIntegration = async () => {
    if (!pendingCredentials) {
      return;
    }

    setConsentError("");
    setIsApproving(true);

    try {
      const response = await apiClient("login-token", "POST", null, {
        email: pendingCredentials.email,
        password: pendingCredentials.password,
      });

      if (!response.status) {
        const { message } = resolveLoginError(response);
        throw new Error(message);
      }

      resetConsentFlow();
      setEmail("");
      setPassword("");
      setIsLoggedIn(true);
    } catch (error) {
      console.error("Integration error:", error);
      const { message } = resolveLoginError(error);
      setConsentError(message);
    } finally {
      setIsApproving(false);
    }
  };

  return (
    <>
      <Dialog
        open={Boolean(loginOpen)}
        onClose={onLoginClose}
        className="adflipr-login-dialog"
        PaperProps={{ className: "adflipr-login-dialog__paper" }}
        maxWidth={false}
      >
        <button
          type="button"
          className="adflipr-login-dialog__close"
          onClick={onLoginClose}
          aria-label="Close"
        >
          <IconCross className="icon24" />
        </button>
        <div className="adflipr-login-dialog__content">
          <h2 className="adflipr-login-dialog__title">Login</h2>
          <p className="adflipr-login-dialog__subtitle">
            Get instant access to your adflipr account
          </p>
          <form
            className="adflipr-login-dialog__form"
            noValidate
            onSubmit={handleSubmit}
          >
            <FormError
              message={error}
              className="adflipr-login-dialog__error-banner"
            />
            <div className="input-container">
              <label className="email-dialog-label" htmlFor="adflipr-login-email">
                Email Id
              </label>
              <input
                id="adflipr-login-email"
                className={`email-dialog-input ${
                  errors?.email ? "box-error" : ""
                }`}
                placeholder="Type here..."
                type="text"
                value={email}
                onChange={(e) => {
                  setEmail(e.target.value);
                  setError("");
                  setErrors((prevErrors) => ({
                    ...prevErrors,
                    email: "",
                  }));
                }}
              />
              {errors?.email && (
                <div className="error-msg">{errors?.email}</div>
              )}
            </div>
            <div className="input-container">
              <label
                className="email-dialog-label"
                htmlFor="adflipr-login-password"
              >
                Password
              </label>
              <input
                id="adflipr-login-password"
                className={`email-dialog-input ${
                  errors?.password ? "box-error" : ""
                }`}
                placeholder="Type here..."
                type="password"
                value={password}
                onChange={(e) => {
                  setPassword(e.target.value);
                  setError("");
                  setErrors((prevErrors) => ({
                    ...prevErrors,
                    password: "",
                  }));
                }}
              />
              {errors?.password && (
                <div className="error-msg">{errors?.password}</div>
              )}
            </div>
            <Button
              type="submit"
              fullWidth
              className="btn btn-primary adflipr-login-dialog__submit"
              variant="contained"
              disableRipple
              disableElevation
              disabled={isLoading}
            >
              {isLoading ? <Progress button={true} /> : null}
              <span
                className={
                  isLoading ? "adflipr-login-dialog__submit-label--hidden" : ""
                }
              >
                Login
              </span>
            </Button>
          </form>
        </div>
      </Dialog>

      <ConnectConsentModal
        open={consentOpen}
        onClose={handleConsentDismiss}
        onApprove={handleApproveIntegration}
        error={consentError}
        isLoading={isApproving}
      />

      <div className="home-container">
        <div className="home-details">
          <TodoListPreview onTaskAction={onLoginOpen} />
          <div className="cards-container">
            <div className="left-cards-container">
              <PerformanceMetrics staticEmpty />
              <RecentCard
                title="Recent Campaigns"
                linkText="View all Campaigns"
                imageText="No campaigns created"
                description="Once your campaign is published, you can start receiving data."
                buttonText="Create Campaign"
                iconSrc={ImageNoSearch}
                containerHeight={402}
                path="/campaigns"
                campaigns={[]}
                isLoading={false}
                onEmptyButtonClick={onLoginOpen}
              />
              <RecentCard
                title="Recent Carts"
                linkText="View all Carts"
                imageText="No carts created"
                buttonText="Connect Your Store"
                description="Whenever a cart is abandoned by a customer, we will immediately track it and display the cart details for you."
                iconSrc={ImageNoSearch}
                containerHeight={402}
                path="/app-integration"
                carts={[]}
                isLoading={false}
                onEmptyButtonClick={onLoginOpen}
              />
              <RecentCard
                title="Recent Automations"
                linkText="View all Automations"
                imageText="No automations available"
                buttonText="Create Automation"
                description="Once your automation is published, you can start receiving data."
                iconSrc={ImageNoSearch}
                containerHeight={402}
                automations={[]}
                path="/automations"
                isLoading={false}
                onEmptyButtonClick={onLoginOpen}
              />
            </div>
            <div className="right-cards-container">
              <RecentCard
                title="Recent Activities"
                imageText="No recent acitvity"
                iconSrc={ImageNoSearch}
                containerHeight={618}
                activities={[]}
                isLoading={false}
              />
              <AnalyticsCard
                title="Campaign Sales"
                centralValue="0"
                lastMonth="0"
                today="0"
                isLoading={false}
              />
              <AnalyticsCard
                title="Cart Recovery"
                centralValue="0"
                lastMonth="0"
                today="0"
                isLoading={false}
              />
              <QuickLinks />
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default Login;
