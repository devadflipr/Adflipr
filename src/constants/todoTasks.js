export const TASK_PATHS = {
  createFirstContact: "contacts/allContacts",
  connectStore: "app-integration",
  connectDomain: "settings",
  addBusinessDetails: "profile",
};

export const TODO_TASKS = [
  {
    id: "createFirstContact",
    title: "Create First Contact",
    description:
      "Add All Details About Your Contacts; Track All Activities Between You and Your Customer",
    actionLabel: "Create",
    path: TASK_PATHS.createFirstContact,
  },
  {
    id: "connectStore",
    title: "Connect Your Store",
    description:
      "Connect your Shopify or WooCommerce store for personalized email marketing with cart abandonment tracking, product fetching, and dynamic coupon integration.",
    actionLabel: "Connect",
    path: TASK_PATHS.connectStore,
  },
  {
    id: "connectDomain",
    title: "Connect Your Domain",
    description:
      "By sending email with your own domain, it gains the users trust and boosts the conversions",
    actionLabel: "Connect",
    path: TASK_PATHS.connectDomain,
  },
  {
    id: "addBusinessDetails",
    title: "Add Business Details",
    description:
      "As per Gmail, Outlook, and Yahoo spam compliance, you need to add your business name and address in the email.",
    actionLabel: "Add",
    path: TASK_PATHS.addBusinessDetails,
  },
];

export const TASK_ID_TO_STATUS = {
  createFirstContact: "isContactCreated",
  connectStore: "isStoreConnected",
  connectDomain: "isDomainConnected",
  addBusinessDetails: "isBusinessDetailsAvailable",
};
