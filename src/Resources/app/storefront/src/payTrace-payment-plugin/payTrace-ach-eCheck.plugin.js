import Plugin from 'src/plugin-system/plugin.class';

export default class PayTraceAchECheckPlugin extends window.PluginBaseClass {
    static options = {
        confirmFormId: 'confirmOrderForm',
        parentCreditCardWrapperId: 'payTrace_payment',
        publicKeyUrl: 'https://api.sandbox.paytrace.com/v3/e2ee/public-key.pem',
    };

    init() {
        this._registerElements();
        this._bindEvents();
        this._loadPayTraceKey();
    }

    _registerElements() {
        this.confirmOrderForm = document.forms[this.options.confirmFormId];
        this.parentCreditCardWrapper = document.getElementById(this.options.parentCreditCardWrapperId);
        this.amount = this.parentCreditCardWrapper.getAttribute('data-amount');
        this.errorEl = document.getElementById('error-message');
    }

    _loadPayTraceKey() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);
    }

    _bindEvents() {
        document.getElementById("submit-ach-payment").addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._getEncryptedTokens();
        });
    }

    _getEncryptedTokens() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);

        const routingField = document.getElementById('achRoutingNumber');
        const accountField = document.getElementById('achAccountNumber');
        const billingName = document.getElementById('ach-full-name').value;

        if (!routingField || !accountField) {
            console.error('Input fields not found!');
            this._showError('Form fields are missing.');
            return;
        }

        const encryptedRouting = paytrace.encryptValue(routingField.value);
        const encryptedAccount = paytrace.encryptValue(accountField.value);

        this._submitPayment(encryptedRouting, encryptedAccount, billingName);
    }

    _submitPayment(encryptedRouting, encryptedAccount, billingName) {
        const payload = {
            billingName: billingName,
            routingNumber: encryptedRouting,
            accountNumber: encryptedAccount,
            amount: this.amount
        };

        fetch('/process-echeck-deposit', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Transaction Successful:', data);
                    this._hideError();

                    if (this.confirmOrderForm) {
                        this.confirmOrderForm.submit();
                    }
                } else {
                    console.error('Payment failed:', data.message || 'Unknown error');
                    this._showError(data.message || 'Payment failed.');
                }
            })
            .catch(error => {
                console.error('Payment submission failed:', error);
                this._showError('An unexpected error occurred while submitting the payment.');
            });
    }

    _showError(message) {
        if (this.errorEl) {
            this.errorEl.innerHTML = message;
            this.errorEl.classList.remove('d-none');
        }
    }

    _hideError() {
        if (this.errorEl) {
            this.errorEl.innerHTML = '';
            this.errorEl.classList.add('d-none');
        }
    }
}
