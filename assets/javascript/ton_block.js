let wc_ton_payment_gateway = 'wc_ton';//name of payment gateway
let wc_ton_settings  = window.wc.wcSettings.getSetting( wc_ton_payment_gateway+'_data', {} );
let wc_ton_label     = window.wp.htmlEntities.decodeEntities( wc_ton_settings.title ) || window.wp.i18n.__( 'TON', wc_ton_payment_gateway );
let wc_ton_content = () => {
    return window.wp.htmlEntities.decodeEntities( wc_ton_settings.description || '' );
};
let wc_ton_block_gateway = {
    name: wc_ton_payment_gateway,
    label: wc_ton_label,
    content: Object( window.wp.element.createElement )( wc_ton_content, null ),
    edit: Object( window.wp.element.createElement )( wc_ton_content, null ),
    canMakePayment: () => true,
    ariaLabel: wc_ton_label,
    supports: {
        features: wc_ton_settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( wc_ton_block_gateway );