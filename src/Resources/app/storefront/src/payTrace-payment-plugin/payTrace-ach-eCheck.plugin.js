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
        this.confirmOrderForm =
            document.getElementById(this.options.confirmFormId) ||
            document.forms[this.options.confirmFormId];
        this.parentCreditCardWrapper = document.getElementById(this.options.parentCreditCardWrapperId);
        this.amount = this.parentCreditCardWrapper?.getAttribute('data-amount') || '';
        this.errorEl = document.getElementById('ach-error-message');
        this.jsDataEl = document.getElementById('paytrace-ach-jsData');
        this.translations = this.jsDataEl ? JSON.parse(this.jsDataEl.dataset.jsdata).translations : {};

        this.submitBtn =
            document.getElementById('confirmFormSubmit') ||
            document.querySelector('button[type="submit"][form="confirmOrderForm"]') ||
            this.confirmOrderForm?.querySelector('button[type="submit"]');

        if (this.submitBtn && !this.submitBtn.dataset.originalHtml) {
            this.submitBtn.dataset.originalHtml = this.submitBtn.innerHTML;
            this.submitBtn.dataset.originalClass = this.submitBtn.className;
        }

        this._loadingClasses = [
            'is-loading','btn-loading','loading','is--loading','is-busy','busy',
            'btn--loading','is-ajax-submit','ajax','loading-indicator'
        ];
        this._spinnerSelectors = [
            '.spinner-border','.spinner-grow','.icon--loading','.icon-loading','.loading-icon'
        ];
    }

    _loadPayTraceKey() {
        paytrace.setKeyAjax(this.options.publicKeyUrl);
    }

    _bindEvents() {
        if (!this.confirmOrderForm) return;

        this.confirmOrderForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();

            this._hideError()

            const cardFormContainer = document.getElementById(this.options.parentCreditCardWrapperId);
            if (cardFormContainer){
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

        const routingValue = document.getElementById('achRoutingNumber')?.value?.trim();
        const accountEl = document.getElementById('achAccountNumber');
        const billingName = document.getElementById('ach-full-name')?.value?.trim() || '';
        const accountType = document.querySelector('input[name="accountType"]:checked')?.value;

        if (!routingField || !accountField || !accountType) {
            this._hideLoading();
            this._showError(this._t('submitErrorMissingFields'));
            return;
        }

        const encryptedRouting = routingValue;
        const encryptedAccount = paytrace.encryptValue(accountEl.value);

        this._submitPayment(encryptedRouting, encryptedAccount, billingName, accountType);
    }

    _submitPayment(encryptedRouting, encryptedAccount, billingName, accountType) {
        const flow = document.getElementById('payTrace_payment').getAttribute('data-flow');

        const payload = {
            billingName: billingName,
            routingNumber: encryptedRouting,
            accountNumber: encryptedAccount,
            accountType: accountType,
            amount: this.amount
        };

        if (flow === 'order_payment') {
            document.getElementById('paytracePaymentData').value = JSON.stringify(payload);
            document.getElementById('confirmOrderForm').submit();
        } else {
            fetch('/process-echeck-deposit', {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {'Content-Type': 'application/json'}
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
                        this._showNote()
                        this._showError(data.message || this._t('submitErrorPaymentFailed'));
                    }
                })
                .catch(error => {
                    this._showError(this._t('submitErrorUnknown') + ' | ' + error);
                });
        }
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
    }
    _forceStopLoading() {
        const loader = document.getElementById('paytrace-loading-indicator');
        if (loader) loader.classList.add('d-none');

        if (this.submitBtn) {
            this._spinnerSelectors.forEach(sel => {
                this.submitBtn.querySelectorAll(sel).forEach(el => el.remove());
            });

            if (this.submitBtn.dataset.originalHtml) {
                this.submitBtn.innerHTML = this.submitBtn.dataset.originalHtml;
            } else {
                this.submitBtn.textContent = (this.submitBtn.textContent || '').trim() || 'Submit order';
            }
            if (this.submitBtn.dataset.originalClass) {
                this.submitBtn.className = this.submitBtn.dataset.originalClass;
            }

            this.submitBtn.removeAttribute('aria-busy');
            this.submitBtn.removeAttribute('data-is-loading');
            this.submitBtn.disabled = false;
            this._loadingClasses.forEach(c => this.submitBtn.classList.remove(c));
        }

        const roots = [this.confirmOrderForm, this.parentCreditCardWrapper, document.body].filter(Boolean);
        for (const root of roots) {
            this._loadingClasses.forEach(c => {
                root.classList?.remove?.(c);
                root.querySelectorAll?.(`.${c}`)?.forEach(el => el.classList.remove(c));
            });
            root.querySelectorAll?.('[data-is-loading]')?.forEach(el => el.removeAttribute('data-is-loading'));
            root.querySelectorAll?.('[aria-busy="true"]')?.forEach(el => el.removeAttribute('aria-busy'));
            this._spinnerSelectors.forEach(sel => root.querySelectorAll?.(sel)?.forEach(el => el.remove()));
        }

        const note = document.getElementById('paytrace-card-note-message');
        if (note) note.classList.add('d-none');

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
        const dict= this.translations || {};
        return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key;
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
