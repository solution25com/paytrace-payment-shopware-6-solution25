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
        if (!this.confirmOrderForm) return;

        this.confirmOrderForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._disableSubmit();
            this._getEncryptedTokens();
        });
    }

    _getEncryptedTokens() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);

        const routingField = document.getElementById('achRoutingNumber').value;
        const accountField = document.getElementById('achAccountNumber');
        const billingName = document.getElementById('ach-full-name').value;

        if (!routingField || !accountField) {
            this._showError('Form fields are missing.');
            return;
        }

        const encryptedRouting = routingField;
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
                  this._hideError();
                  this.confirmOrderForm.submit();
              } else {
                  this._showError(data.message || 'Payment failed.');
              }
          })
          .catch(error => {
              this._showError('An unexpected error occurred while submitting the payment. | ' + {error});
          });
    }

    _showError(message) {
        if (this.errorEl) {
            this.errorEl.innerHTML = message;
            this.errorEl.classList.remove('d-none');
        }

        this._enableSubmit();
    }

    _hideError() {
        if (this.errorEl) {
            this.errorEl.innerHTML = '';
            this.errorEl.classList.add('d-none');
        }

        this._enableSubmit();
    }

    _disableSubmit() {
        const confirmButton = this.confirmOrderForm.querySelector('button[type="submit"]');
        if (confirmButton) {
            confirmButton.disabled = true;
        }
    }

    _enableSubmit() {
        const confirmButton = this.confirmOrderForm.querySelector('button[type="submit"]');
        if (confirmButton) {
            confirmButton.disabled = false;
        }
    }

}
