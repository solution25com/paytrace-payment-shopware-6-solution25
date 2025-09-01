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
        this.errorEl = document.getElementById('ach-error-message');
        this.jsDataEl = document.getElementById('paytrace-ach-jsData');
        this.translations = this.jsDataEl ? JSON.parse(this.jsDataEl.dataset.jsdata).translations : {};
    }

    _loadPayTraceKey() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);
    }

    _bindEvents() {
        if (!this.confirmOrderForm) return;

        this.confirmOrderForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const cardFormContainer = document.getElementById(this.options.parentCreditCardWrapperId);
            if (cardFormContainer) {
                cardFormContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            this._disableSubmit();
            this._disableFormInputs();
            this._showLoading();
            this._getEncryptedTokens();
        });
    }

    _getEncryptedTokens() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);

        const routingField = document.getElementById('achRoutingNumber').value;
        const accountField = document.getElementById('achAccountNumber');
        const billingName = document.getElementById('ach-full-name').value;
        const accountType = document.querySelector('input[name="accountType"]:checked')?.value;

        if (!routingField || !accountField || !accountType) {
            this._hideLoading();
            this._showError(this._t('paytrace_shopware6.ach_echeck.submitError.missingFields'));
            return;
        }

        const encryptedRouting = routingField;
        const encryptedAccount = paytrace.encryptValue(accountField.value);

        this._submitPayment(encryptedRouting, encryptedAccount, billingName, accountType);
    }

    _submitPayment(encryptedRouting, encryptedAccount, billingName, accountType) {
        const payload = {
            billingName: billingName,
            routingNumber: encryptedRouting,
            accountNumber: encryptedAccount,
            accountType: accountType,
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
                  this._hideLoading();
                  this.confirmOrderForm.submit();
                  this._hideLoading();
              } else {
                  this._hideLoading();
                  this._showError(data.message || this._t('paytrace_shopware6.ach_echeck.submitError.paymentFailed'));
              }
          })
          .catch(error => {
              this._showError(this._t('paytrace_shopware6.ach_echeck.submitError.unknown') + ' | ' + error);
          });
    }

    _showError(message) {
        if (this.errorEl) {
            this.errorEl.innerHTML = message;
            this.errorEl.classList.remove('d-none');
        }

        this._enableSubmit();
        this._enableFormInputs();
    }

    _hideError() {
        if (this.errorEl) {
            this.errorEl.innerHTML = '';
            this.errorEl.classList.add('d-none');
        }

        this._enableSubmit();
        this._enableFormInputs();
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

    _t(key) {
        return this.translations[key] || key;
    }

    _showLoading() {
        const loader = document.getElementById('paytrace-loading-indicator');
        if (loader) {
            loader.classList.remove('d-none');
        }
    }

    _hideLoading() {
        const loader = document.getElementById('paytrace-loading-indicator');
        if (loader) {
            loader.classList.add('d-none');
        }
    }

    _disableFormInputs() {
        const inputs = this.parentCreditCardWrapper.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            input.disabled = true;
        });
    }

    _enableFormInputs() {
        const inputs = this.parentCreditCardWrapper.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            input.disabled = false;
        });
    }

}
