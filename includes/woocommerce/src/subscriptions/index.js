import { decodeEntities } from "@wordpress/html-entities";

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting("trustistecommerce_payment_gateway_subscriptions_data", {});

const label = decodeEntities(settings.title);

const Content = () => {
  return decodeEntities(settings.description || "");
};

const Label = () => {
  return (
    <span style={{ width: "100%" }}>
      {label}
      <Icon />
    </span>
  );
};

const Icon = () => {
	return settings.icon
		? <img src={settings.icon} style={{ float: 'right', marginRight: '20px' }} />
		: ''
}

registerPaymentMethod({
  name: "trustistecommerce_payment_gateway_subscriptions",
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: function ({ cart, cartTotals, cartNeedsShipping }) {
    // if cart contains more than one item return false
    if (Array.isArray(cart?.cartItems) && cart.cartItems.length > 1) {
      return false;
    }
    // if cart contains an item with type subscription return true
    if (Array.isArray(cart?.cartItems) && cart.cartItems[0].type === "subscription") {
      return true;
    }
    return false;
  },
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
});