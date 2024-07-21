import { decodeEntities } from '@wordpress/html-entities';
const { registerPaymentMethod } = window.wc.wcBlocksRegistry
const { getSetting } = window.wc.wcSettings

const settings = getSetting('moneroo_wc_woocommerce_plugin_data', {})
const defaultLabel = __(
    'Pay securely with your Mobile Money account, credit card, bank account or other payment methods.',
    'moneroo-woocommerce'
);
const label = decodeEntities(settings.title) || defaultLabel

const Content = () => {
    return decodeEntities(settings.description || '')
}

const Icon = () => {
    return settings.icon
        ? <img src={settings.icon} style={{ float: 'right', marginLeft: '20px' }} />
        : ''
}

const Label = (props) => {
    const { PaymentMethodLabel } = props.components
    return <><PaymentMethodLabel text={label} /> <Icon /></>
}

registerPaymentMethod({
    name: "moneroo_wc_woocommerce_plugin",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    }
})