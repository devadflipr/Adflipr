import React from "react";
import { getAppBaseUrl } from "../../utils";

const linkStyle = {
  color: "#565ADD",
  textDecoration: "none",
  cursor: "pointer",
};

const linkButtonStyle = {
  ...linkStyle,
  background: "transparent",
  border: "none",
  padding: 0,
  font: "inherit",
};

const ActivityDescription = ({ activity }) => {
  const redirectToNewTab = (url) => {
    window.open(`${getAppBaseUrl()}/app${url}`, "_blank");
  };

  const handleAbandonIdClick = (tab = 0) => {
    redirectToNewTab(`/abandoned-cart?tab=${tab}`);
  };

  const handleContactClick = (id, tab = 0) => {
    if (id) {
      redirectToNewTab(`/contacts/allContacts/${id}?tab=${tab}`);
    }
  };

  const handleCampaignClick = (id) => {
    if (id) {
      redirectToNewTab(`/campaigns/charts/${id}`);
    }
  };

  if (!activity) return null;

  switch (activity.type) {
    case "PROFILE_ADDED":
      return (
        <div>
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id);
            }}
          >
            {activity.metadata?.contact_name}
          </button>{" "}
          has recently joined
        </div>
      );

    case "NOTE_ADDED":
      return (
        <div>
          Added note on{" "}
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id);
            }}
          >
            {activity.metadata?.contact_name}
          </button>{" "}
          {activity.metadata?.note_description}
        </div>
      );

    case "ORDERED":
      return (
        <div>
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id);
            }}
          >
            {activity.metadata?.contact_name}
          </button>{" "}
          has made a new purchase, Order Id{" "}
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id, 4);
            }}
          >
            #{activity.metadata?.order_id}
          </button>
        </div>
      );

    case "ABANDONED":
      return (
        <div>
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id);
            }}
          >
            {activity.metadata?.contact_name}
          </button>{" "}
          has abandoned the cart{" "}
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleAbandonIdClick();
            }}
          >
            #{activity.metadata?.transaction_id}
          </button>
        </div>
      );

    case "RECOVERED":
      return (
        <div>
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleContactClick(activity.metadata?.contact_id);
            }}
          >
            {activity.metadata?.contact_name}
          </button>{" "}
          has recovered an abandoned order{" "}
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleAbandonIdClick(1);
            }}
          >
            #{activity.metadata?.transaction_id}
          </button>
        </div>
      );

    case "PUBLISHED":
      return (
        <div>
          Email campaign{" "}
          <button
            type="button"
            style={linkButtonStyle}
            onClick={(e) => {
              e.preventDefault();
              handleCampaignClick(activity.metadata?.campaign_id);
            }}
          >
            {activity.metadata?.campaign_name}
          </button>{" "}
          is Published
        </div>
      );

    default:
      return null;
  }
};

export default ActivityDescription;
