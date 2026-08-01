import React, { useEffect, useState } from "react";
import Progress from "../../components/Progress";
import { apiClient } from "../../utils";

function formatPercentForDisplay(value) {
  if (value == null || value === "-") {
    return "-";
  }
  if (typeof value === "number") {
    return `${value.toFixed(2)}%`;
  }
  const text = String(value).trim();
  if (!text) {
    return "-";
  }
  return text.includes("%") ? text : `${text}%`;
}

function convertData(data) {
  return [
    { label: "Sent", value: data.numOfEmailSent || "-" },
    {
      label: "Delivered",
      value: data.deliveredPercentage || "-",
      number: data.numOfDeliveredEmail,
    },
    {
      label: "Open Rate",
      value: data.openPercentage || "-",
      number: data.numOfOpenedEmail,
    },
    {
      label: "Click Rate",
      value: data.clickPercentage || "-",
      number: data.numOfClickedEmail,
    },
    {
      label: "Click to Open Rate",
      value:
        data.numOfClickedEmail && data.numOfOpenedEmail
          ? `${((data.numOfClickedEmail / data.numOfOpenedEmail) * 100).toFixed(
              2,
            )}%`
          : "-",
    },
    { label: "Unsubscribe Rate", value: "-" },
    {
      label: "Soft Bounce",
      value: formatPercentForDisplay(data.softBouncePercentage),
      number: data.numOfSoftBouncedEmail,
    },
    {
      label: "Hard Bounce",
      value: formatPercentForDisplay(data.hardBouncePercentage),
      number: data.numOfHardBouncedEmail,
    },
    { label: "Revenue", value: "-" },
  ];
}

const EMPTY_METRICS = [
  { label: "Sent", value: "-" },
  { label: "Delivered", value: "-" },
  { label: "Open Rate", value: "-" },
  { label: "Click Rate", value: "-" },
  { label: "Click to Open Rate", value: "-" },
  { label: "Unsubscribe Rate", value: "-" },
  { label: "Soft Bounce", value: "-" },
  { label: "Hard Bounce", value: "-" },
  { label: "Revenue", value: "-" },
];

const PerformanceMetrics = ({ staticEmpty = false, onApiError }) => {
  const [metrics, setMetrics] = useState(staticEmpty ? EMPTY_METRICS : []);
  const [isLoading, setIsLoading] = useState(false);

  const fetchMetrics = () => {
    setIsLoading(true);
    apiClient("proxy/users/email/stats")
      .then((data) => {
        const temp = convertData(data);
        setMetrics(temp);
      })
      .catch((err) => {
        console.log("Error fetching metrics:", err);
        onApiError?.(err);
      })
      .finally(() => setIsLoading(false));
  };

  useEffect(() => {
    if (staticEmpty) {
      return;
    }
    fetchMetrics();
  }, [staticEmpty]);

  return (
    <div className="performance-container">
      <h3 className="performance-title">Email Performance</h3>
      {isLoading ? (
        <Progress />
      ) : (
        <div className="metrics-grid">
          {metrics.map((metric) => (
            <div className="metric-item" key={metric.label}>
              <h3 className="metric-value">
                {metric.value}
                <span className="metric-number">
                  {metric?.number != null && ` (${metric.number})`}
                </span>
              </h3>
              <p className="metric-label">{metric.label}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default PerformanceMetrics;
