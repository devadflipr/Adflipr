/**
 * Parse API / ISO strings (UTC when no offset), epoch values, or Date in local time.
 * Matches adflipr-app date-utils contract for backend LocalDateTime JSON without zone.
 */
function coerceToDate(input) {
  if (input == null) {
    return null;
  }
  if (input instanceof Date) {
    return Number.isNaN(input.getTime()) ? null : input;
  }
  if (typeof input === "number") {
    const date = new Date(input);
    return Number.isNaN(date.getTime()) ? null : date;
  }
  if (typeof input !== "string") {
    return null;
  }

  const trimmed = input.trim();
  if (trimmed === "" || trimmed === "-") {
    return null;
  }

  if (/^\d+$/.test(trimmed)) {
    const date = new Date(Number(trimmed));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  let utcString = trimmed;
  if (!utcString.endsWith("Z") && !/[-+]\d{2}:\d{2}$/.test(utcString)) {
    utcString = `${utcString}Z`;
  }
  const date = new Date(utcString);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(dateInput, includeTime = false, note = false) {
  const date = coerceToDate(dateInput);
  if (!date) {
    return "-";
  }

  const monthNames = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sept",
    "Oct",
    "Nov",
    "Dec",
  ];

  const day = date.getDate();
  const month = monthNames[date.getMonth()];
  const year = date.getFullYear();

  let formattedDate = `${month} ${day}, ${year}`;

  if (includeTime || note) {
    let hours = date.getHours();
    const minutes = date.getMinutes();

    const ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12;
    hours = hours ? hours : 12;

    const formattedMinutes = minutes < 10 ? "0" + minutes : minutes;
    const time = `${hours}:${formattedMinutes} ${ampm}`;
    formattedDate = note
      ? `${formattedDate} at ${time}`
      : `${formattedDate}, ${time}`;
  }

  return formattedDate;
}

export default formatDate;
