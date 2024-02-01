

const settings = window.wc.wcSettings.getSetting('moneroo_wc_woocommerce_plugin_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Pay securely with your Mobile Money account, credit card, bank account, or other payment methods.', 'moneroo-woocommerce');
const logoUrl = settings['logo_url'] || '';

// Define Content component
const labelContent = (logoUrl, label) => {
    return window.wp.element.createElement(
        'div',
        { style: { display: 'flex', flexDirection: 'row', gap: '0.5rem' } },
        window.wp.element.createElement(
            'div',
            null,
            window.wp.htmlEntities.decodeEntities(settings.description || '')
        ),
        window.wp.element.createElement(
            'div',
            { style: { display: 'flex', flexDirection: 'row', gap: '0.5rem', flexWrap: 'wrap' } },
            window.wp.element.createElement('img', { src: logoUrl, alt: label })
        )
    );
};


const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};

const Moneroo_WC_Gateway_Blocks = {
    name: 'moneroo-wc-blocks',
    label: labelContent(logoUrl, label),
    placeOrderButtonLabel: "Proceed to PayPal",
    content: Object (window.wp.element.createElement )( Content, null),
    edit: Object (window.wp.element.createElement )( Content, null),
    canMakePayment: () => true,
    ariaLabel: window.wp.htmlEntities.decodeEntities(
        'Payment via PayPal'
    ),
    supports: {
        features: settings.supports ?? [],
    },
};



window.wc.wcBlocksRegistry.registerPaymentMethod(Moneroo_WC_Gateway_Blocks);
