import Plugin from 'src/plugin-system/plugin.class';

export default class PayTraceCreditCardPlugin extends Plugin {
    static options = {
        confirmFormId: 'confirmOrderForm',
        parentCreditCardWrapperId: 'payTrace_payment'
    };

    _registerElements() {
        this.confirmOrderForm = document.forms[this.options.confirmFormId];
        this.parentCreditCardWrapper = document.getElementById(this.options.parentCreditCardWrapperId);
        this.clientKey = this.parentCreditCardWrapper.getAttribute('data-client-key');
        this.amount = this.parentCreditCardWrapper.getAttribute('data-amount');
    }

    init() {
        this._registerElements();
        this._setupPayTrace();
        this._bindEvents();
    }

    _setupPayTrace() {
        PTPayment.setup({
            styles: {
                'code': {
                    'font_color':'#5D99CA',
                    'font_size':'13pt',
                    'input_font':'serif, cursive, fantasy',
                    'input_font_weight':'700',
                    'input_margin':'5px 0px 5px 20px',
                    'input_padding':'0px 5px 0px 5px',
                    'label_color':'#5D99CA',
                    'label_size':'16px',
                    'label_width':'150px',
                    'label_font':'sans-serif, arial, serif',
                    'label_font_weight':'bold',
                    'label_margin':'5px 0px 0px 20px',
                    'label_padding':'2px 5px 2px 5px',
                    'background_color':'white',
                    'height':'25px',
                    'width':'110px',
                    'padding_bottom':'2px'
                },
                'cc': {
                    'font_color':'#5D99CA',
                    'font_size':'13pt',
                    'input_font':'Times New Roman, arial, fantasy',
                    'input_font_weight':'400',
                    'input_margin':'5px 0px 5px 0px',
                    'input_padding':'0px 5px 0px 5px',
                    'label_color':'#5D99CA',
                    'label_size':'16px',
                    'label_width':'150px',
                    'label_font':'Times New Roman, sans-serif, serif',
                    'label_font_weight':'light',
                    'label_margin':'5px 0px 0px 0px',
                    'label_padding':'0px 5px 0px 5px',
                    'background_color':'white',
                    'height':'25px',
                    'width':'320px',
                    'padding_bottom':'0px'
                },
                'exp': {
                    'font_color':'#5D99CA',
                    'font_size':'12pt',
                    'input_border_radius':'0px',
                    'input_border_width':'2px',
                    'input_font':'arial, cursive, fantasy',
                    'input_font_weight':'400',
                    'input_margin':'5px 0px 5px 0px',
                    'input_padding':'0px 5px 0px 5px',
                    'label_color':'#5D99CA',
                    'label_size':'16px',
                    'label_width':'150px',
                    'label_font':'arial, fantasy, serif',
                    'label_font_weight':'normal',
                    'label_margin':'5px 0px 0px 0px',
                    'label_padding':'2px 5px 2px 5px',
                    'background_color':'white',
                    'height':'25px',
                    'width':'85px',
                    'padding_bottom':'2px',
                    'type':'dropdown'
                },
                'body': {
                    'background_color':'white'
                }
            },
            authorization: {
                clientKey: this.clientKey
            }
        })
            .then(() => {
                console.log('PayTrace setup complete');
            })
            .catch((error) => {
                console.error('Error during PayTrace setup:', error);
                this._handleError(error);
            });
    }

    _bindEvents() {
        document.getElementById("ProtectForm").addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._getCardToken();
        });

        this.confirmOrderForm.addEventListener("submit", () => {
        });
    }

    _getCardToken() {
        PTPayment.process()
            .then((result) => {
                console.log('Token received:', result);
                if (result.message) {
                    this._submitPayment(result.message);
                } else {
                    console.error('Failed to receive a token:', result);
                    this._handleError('Token generation failed');
                }
            })
            .catch((error) => {
                console.error('Error during payment processing:', error);
                this._handleError(error);
            });
    }

    _submitPayment(token) {
        fetch('/capture', {
            method: 'POST',
            body: JSON.stringify({ token: token, amount: this.amount }),
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Transaction Successful:', data);
                    document.getElementById('payTrace-transaction-id').value = data.transactionId;
                    document.getElementById('confirmOrderForm').submit();
                } else {
                    console.error('Payment failed:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Payment submission failed:', error);
            });
    }

    _handleError(error) {
        console.error('Error during card processing:', error);
    }
}
