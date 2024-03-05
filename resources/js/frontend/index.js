
import { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'divido-finance_data', {} );

const defaultLabel = __(
    'Finance',
    'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
const logoUrl = settings.logo
const lender = decodeEntities( settings.description) || defaultLabel;
const description = decodeEntities( settings.description || '' );
const footnote = decodeEntities( settings.footnote || '' );
const plans = (settings.plans) ? settings.plans : "";

const FinanceWidget = ({price}) => {
    return <div id="financeWidget"
        data-calculator-widget
        data-mode="calculator"
        data-amount={price}
        data-plans={plans}
        data-footnote={footnote}
    />
};

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
    const containerStyle =  {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        width: "100%"
    }

    return <div style={containerStyle}>
        <div >{ label }</div>
        { logoUrl && (
            <img src={ logoUrl } alt={ lender } style={{marginRight: "16px"}} />
        )}
    </div>;
};

/**
 * Content component
 */
const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentProcessing } = eventRegistration;
    useEffect( () => {
        if(typeof __widgetInstance !== 'undefined'){
            __widgetInstance.init();
        } else if (typeof __calculator !== 'undefined') {
            __calculator.init();
        }
        const processing = onPaymentProcessing( async () => {
            if(document.getElementsByName('divido_plan').length <= 0){
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: 'Unable to receive the chosen finance plan'
                }
            }
            const plan = document.getElementsByName('divido_plan')[0].value
            const deposit = (document.getElementsByName('divido_deposit').length > 0)
                ? document.getElementsByName('divido_deposit')[0].value
                : 0;
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        "divido_plan": plan,
                        "divido_deposit": deposit
                    },
                },
            };
        });

        return () => {
            processing();
        }
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentProcessing
    ]);
    return <div>
        <div>{description}</div>
        <FinanceWidget price={props.billing.cartTotal.value} />
    </div>
};

/**
 * Finance payment method config object.
 */
const Finance = {
    name: settings.name,
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => settings.active,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( Finance );
