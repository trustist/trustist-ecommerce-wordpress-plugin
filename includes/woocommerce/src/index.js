import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('trustistecommerce_payment_gateway_data', {});

const label = decodeEntities( settings.title )

const Content = () => {
	return decodeEntities( settings.description || '' )
}

// const Label = ( props ) => {
// 	const { PaymentMethodLabel } = props.components
// 	return <PaymentMethodLabel text={ label } />
// }

const Label = () => {
	return (
        <span style={{ width: '100%' }}>
            {label}
            <Icon />
        </span>
    )
}

const Icon = () => {
	return settings.icon 
		? <img src={settings.icon} style={{ float: 'right', marginRight: '20px' }} /> 
		: ''
}

registerPaymentMethod({
    name: 'trustistecommerce_payment_gateway',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
	supports: {
		features: settings.supports,
	}
});
