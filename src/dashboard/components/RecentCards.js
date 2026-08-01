import { Chip, Typography } from "@mui/material";

import getSymbolFromCurrency from "currency-symbol-map";
import React, { useState } from "react";
import { ReactComponent as IconArrow } from "../../assets/icons/icon-back.svg";
import Progress from "../../components/Progress";
import Status from "../../components/Status";
import Table from "../../components/Table";
import {
  activitySentance,
  renderEmailActivityMessage,
} from "../../utils/activitySentence";
import { formatDate, formatStringToTitleCase, getAppBaseUrl } from "../../utils";
import ActivityDescription from "./RenderActivity";

export function getFirstWord(action) {
  if (!action) return action;

  return action.split("_")[0]; // Extracts the first word before the first underscore
}

export function getInitials(name) {
  if (!name || typeof name !== "string") return "";

  const words = name.trim().split(/\s+/); // Split by spaces, handling multiple spaces
  const firstInitial = words[0]?.charAt(0)?.toUpperCase() || "";
  const lastInitial =
    words?.length > 1
      ? words[words?.length - 1]?.charAt(0)?.toUpperCase() || ""
      : "";

  return firstInitial + lastInitial;
}

const RecentCard = ({
  title,
  buttonText,
  buttonAction,
  imageText,
  // description,
  iconSrc,
  linkText,
  linkAction,
  containerHeight,
  path,
  activities = [],
  carts = [],
  campaigns = [],
  onLoadMore,
  automations = [],
  isLoading = false,
  onEmptyButtonClick,
  hideEmptyStateButton = false,
}) => {
  const [isLoadMoreLoading, setIsLoadMoreLoading] = useState(false);
  const cartTableHeaders = [
    {
      numeric: false,
      key: "name",
      disablePadding: false,
      label: "Name",
      render: (row) => (
        <Typography>
          {row?.metadata?.customer?.first_name ||
          row?.metadata?.customer?.last_name
            ? `${row?.metadata?.customer?.first_name || ""} ${
                row?.metadata?.customer?.last_name || ""
              }`.trim()
            : "-"}
        </Typography>
      ),
    },
    {
      numeric: false,
      key: "email",
      disablePadding: false,
      label: "Email",
      render: (row) => (
        <Typography>
          {row?.metadata?.customer?.email || row.contactEmail || "-"}
        </Typography>
      ),
    },
    {
      numeric: false,
      key: "created",
      disablePadding: false,
      label: "Created on",
      render: (row) =>
        formatDate(row?.metadata?.created_at || row.created, true),
    },
    {
      numeric: false,
      key: "amount",
      disablePadding: false,
      label: "Amount",
      render: (row) => (
        <div>
          {getSymbolFromCurrency(row?.metadata?.presentment_currency)}
          {row.amount}
        </div>
      ),
    },
    {
      numeric: false,
      key: "status",
      disablePadding: false,
      label: "Status",
      render: (row) => (
        <Chip
          label={formatStringToTitleCase(row.status)}
          size="small"
          sx={{
            backgroundColor: row.status === "RECOVERED" ? "#DCF2ED" : "#FFF4DE",
            color: row.status === "RECOVERED" ? "#09B29C" : "#B25E09",
          }}
        />
      ),
    },
  ];

  const campaignTableHeaders = [
    {
      numeric: false,
      key: "name",
      disablePadding: false,
      label: "Name",
      onClick: (row) => {
        if (row.status === "ON_GOING") {
          redirectToNewTab(`/campaigns/charts?id=${row.id}`);
        } else {
          redirectToNewTab(`/campaigns/${row.id}`);
        }
      },
      render: (row) => row.name,
    },
    {
      numeric: false,
      key: "created",
      disablePadding: false,
      label: "Created On",
      render: (row) => formatDate(row.created, true),
    },
    {
      numeric: false,
      key: "contacts",
      disablePadding: false,
      label: "Contacts",
      render: (row) => row?.numberOfContacts ?? "-",
    },
    {
      numeric: false,
      key: "sent",
      disablePadding: false,
      label: "Sent",
      render: (row) => row?.numOfEmailSent ?? 0,
    },
    {
      numeric: false,
      key: "status",
      disablePadding: false,
      label: "Status",
      render: (row) => (
        <div className="table-column">
          {row.status ? (
            <div className={`status ${row.status}`}>
              {formatStringToTitleCase(row.status)}
            </div>
          ) : (
            "_"
          )}
        </div>
      ),
    },
  ];

  const automationTableHeaders = [
    {
      numeric: false,
      key: "name",
      disablePadding: false,
      label: "Name",
      onClick: (row) => redirectToNewTab(`/automations/${row.id}`),
      render: (row) => row.name,
    },
    {
      numeric: false,
      key: "created",
      disablePadding: false,
      label: "Created On",
      render: (row) => formatDate(row.created, true),
    },
    {
      numeric: false,
      key: "status",
      disablePadding: false,
      label: "Status",
      render: ({ status }) => (status ? <Status status={status} /> : "_"),
    },
  ];

  const redirectToNewTab = (url) => {
    window.open(`${getAppBaseUrl()}/app${url}`, "_blank");
  };

  const handleAbandonIdClick = (tab = 0) => {
    redirectToNewTab(`/abandoned-cart?tab=${tab}`);
  };

  const handleContactClick = (id, tab = 0) => {
    redirectToNewTab(`/contacts/allContacts/${id}?tab=${tab}`);
  };

  const renderContent = () => {
    if (title === "Recent Campaigns" && campaigns.length > 0) {
      return (
        <div className="automation-card-body">
          <Table
            headers={campaignTableHeaders}
            rows={campaigns}
            noCheckBox={true}
            isLoading={false}
            ifEmpty={campaigns.length === 0}
          />
        </div>
      );
    }

    if (title === "Recent Carts" && carts.length > 0) {
      return (
        <div className="automation-card-body">
          <Table
            headers={cartTableHeaders}
            rows={carts}
            noCheckBox={true}
            isLoading={false}
            ifEmpty={carts.length === 0}
          />
        </div>
      );
    }

    if (title === "Recent Automations" && automations.length > 0) {
      return (
        <div className="automation-card-body">
          <Table
            headers={automationTableHeaders}
            rows={automations}
            noCheckBox={true}
            isLoading={false}
            ifEmpty={automations.length === 0}
          />
        </div>
      );
    }

    if (activities.length > 0) {
      return (
        <div className="automation-activity-log">
          {activities.map((activity, index) => (
            <div className="activity" key={activity.id ?? `activity-${index}`}>
              <div className="name-initial">
                {getInitials(
                  activity?.metadata?.contact_name ||
                    activity?.metadata?.campaign_name ||
                    "",
                ) || "A"}
              </div>
              <div className="activity-description">
                <div className="activity-message">
                  {getFirstWord(activity?.type) === "EMAIL" ? (
                    renderEmailActivityMessage(
                      activitySentance(
                        activity?.metadata?.contact_name,
                        activity?.type,
                      ),
                      activity?.metadata?.contact_name,
                      activity.metadata?.contact_id,
                      (contactId) => handleContactClick(contactId),
                    )
                  ) : (
                    <ActivityDescription activity={activity} />
                  )}
                </div>
                <span className="activity-created">
                  {formatDate(
                    activity.created != null
                      ? String(activity.created)
                      : undefined,
                    true,
                  )}
                </span>
              </div>
            </div>
          ))}
          {onLoadMore && (
            <div className="activity-footer">
              <button
                type="button"
                onClick={async () => {
                  try {
                    setIsLoadMoreLoading(true);
                    const maybePromise = onLoadMore();
                    if (
                      maybePromise &&
                      typeof maybePromise.then === "function"
                    ) {
                      await maybePromise;
                    }
                  } finally {
                    setIsLoadMoreLoading(false);
                  }
                }}
                className="btn no-border"
                disabled={isLoadMoreLoading}
              >
                {isLoadMoreLoading ? "Loading..." : "Load more"}
              </button>
            </div>
          )}
        </div>
      );
    }

    return (
      <div className="automation-card-body">
        {iconSrc && (
          <img className="automation-icon" src={iconSrc} alt={`No ${title}`} />
        )}
        <div className="automation-card-empty-actions">
          <p className="automation-description">{imageText}</p>
          {/* {description && ( <p className="automation-description">{description}</p>)} */}
          {buttonText && !hideEmptyStateButton && (
            <button
              type="button"
              className="btn btn-recent no-border"
              onClick={() =>
                onEmptyButtonClick
                  ? onEmptyButtonClick()
                  : redirectToNewTab(path)
              }
            >
              {buttonText}
            </button>
          )}
        </div>
      </div>
    );
  };

  return (
    <div
      className="automation-card-container"
      style={{
        height:
          !carts.length && !campaigns.length && !automations.length
            ? `${containerHeight}px`
            : "auto",
      }}
    >
      <div className="automation-card-header">
        <h3>{title}</h3>
        {linkText && (
          <button className="view-all-carts-btn" onClick={linkAction}>
            <span>{linkText}</span>
            <IconArrow className="icon24" />
          </button>
        )}
      </div>
      {isLoading ? <Progress /> : renderContent()}
    </div>
  );
};

export default RecentCard;
