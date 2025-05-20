export default class PayTraceCreditCardPlugin extends window.PluginBaseClass {
    static options = {
        confirmFormId: 'confirmOrderForm',
        parentCreditCardWrapperId: 'payTrace_payment'
    };

    _registerElements() {
        this.confirmOrderForm = document.forms[this.options.confirmFormId];
        this.parentCreditCardWrapper = document.getElementById(this.options.parentCreditCardWrapperId);
        this.clientKey = this.parentCreditCardWrapper.getAttribute('data-client-key');
        this.amount = this.parentCreditCardWrapper.getAttribute('data-amount');
        this.cardsDropdown = this.parentCreditCardWrapper.getAttribute('data-cardsDropdown');
        this.errorEl = document.getElementById('credit-card-error-message');
        this.parentCreditCardWrapper.querySelectorAll('.paytrace-form-container input');
    }

    init() {
        this._registerElements();
        this._registerEvents();
        this._populateDropdown();
        this._setupPayTrace();
    }

    _populateDropdown() {
        const dropdown = document.getElementById('saved-cards');
        if (!dropdown) return;

        const cards = JSON.parse(this.cardsDropdown);
        dropdown.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = this._t('paytrace_shopware6.credit_card.selectSavedCard');
        dropdown.appendChild(defaultOption);

        cards.forEach(card => {
            const option = document.createElement('option');
            option.value = card.vaultedCustomerId;
            option.textContent = card.cardType + ' - **** **** **** ' + card.lastDigits;
            dropdown.appendChild(option);
        });
    }

    _setupPayTrace() {
        PTPayment.setup({
            styles: {
                cc: {
                    background_color: '#ffffff',
                    border_color: '#ced4da',
                    border_style: 'solid',
                    font_color: '#495057',
                    font_size: '14px',
                    input_border_radius: '6px',
                    input_border_width: '1px',
                    input_font: 'Segoe UI, sans-serif',
                    input_font_weight: '400',
                    input_margin: '3px 0px 10px 0px',
                    input_padding: '3px 8px 3px 8px',
                    label_color: '#495057',
                    label_font: 'Segoe UI, sans-serif',
                    label_font_weight: '500',
                    label_size: '12px',
                    label_width: 'auto',
                    label_margin: '0 0 4px 0',
                    label_padding: '0 4px',
                    label_border_style: 'none',
                    height: '30px',
                    width: '95%',
                    padding_bottom: '4px'
                },
                code: {
                    background_color: '#ffffff',
                    border_color: '#ced4da',
                    border_style: 'solid',
                    font_color: '#495057',
                    font_size: '14px',
                    input_border_radius: '6px',
                    input_border_width: '1px',
                    input_font: 'Segoe UI, sans-serif',
                    input_font_weight: '400',
                    input_margin: '5px 0px 10px 0px',
                    input_padding: '4px 8px 4px 8px',
                    label_color: '#495057',
                    label_font: 'Segoe UI, sans-serif',
                    label_font_weight: '500',
                    label_size: '13px',
                    label_width: 'auto',
                    label_margin: '0 0 4px 0',
                    label_padding: '0 4px',
                    label_border_style: 'none',
                    height: '30px',
                    width: '95%',
                    padding_bottom: '4px'
                },
                exp: {
                    background_color: '#ffffff',
                    border_color: '#ced4da',
                    border_style: 'solid',
                    font_color: '#495057',
                    font_size: '14px',
                    input_border_radius: '6px',
                    input_border_width: '1px',
                    input_font: 'Segoe UI, sans-serif',
                    input_font_weight: '400',
                    input_margin: '5px 0px 10px 0px',
                    input_padding: '4px 8px 4px 8px',
                    label_color: '#495057',
                    label_font: 'Segoe UI, sans-serif',
                    label_font_weight: '500',
                    label_size: '13px',
                    label_width: 'auto',
                    label_margin: '0 0 4px 0',
                    label_padding: '0 4px',
                    label_border_style: 'none',
                    height: '40px',
                    width: '40%',
                    padding_bottom: '4px',
                    type: 'dropdown'
                },
                body: {
                    background_color: '#ffffff'
                }
            },            authorization: {
                clientKey: this.clientKey
            }
        })
            .then(() => {
                PTPayment.theme('label-extended-top')
                console.warn('PayTrace setup complete');
            })
            .catch((error) => {
                console.error('Error during PayTrace setup:', error);
                this._showError(this._t('paytrace_shopware6.credit_card.submitError.initFail'));
            });
    }

    _registerEvents() {
        this.confirmOrderForm.addEventListener('submit', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const cardFormContainer = document.getElementById('payTrace_payment');
            if (cardFormContainer) {
                cardFormContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            this._disableSubmit();
            this._showLoading();

            const savedCardSelected = document.getElementById('saved-cards')?.value;

            if (savedCardSelected) {
                this._vaultedPayment();
                return;
            }

            PTPayment.validate((validationErrors) => {
                if (validationErrors.length > 0) {
                    this._handleValidationErrors(validationErrors);
                    this._hideLoading();
                    return;
                }

                this._getCardToken();
            });
        });

        const selectCardBtn = document.getElementById("SelectCardButton");
        const savedCardsDropdown = document.getElementById("saved-cards");

        if (selectCardBtn && savedCardsDropdown) {
            savedCardsDropdown.addEventListener("change", (e) => {
                if (e.target.value) {
                    selectCardBtn.style.display = "block";
                } else {
                    selectCardBtn.style.display = "none";
                }
            });

            selectCardBtn.addEventListener("click", (e) => {
                e.preventDefault();

                if (!this.confirmOrderForm.checkValidity()) {
                    this.confirmOrderForm.reportValidity();
                    return;
                }

                this._vaultedPayment();
            });
        }

        const inputs = this.parentCreditCardWrapper.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('input', () => this._hideError(input));
        });
    }

    _handleValidationErrors(validationErrors) {
        PTPayment.style({
            'cc': { 'border_color': '#ced4da' },
            'exp': { 'border_color': '#ced4da' },
            'code': { 'border_color': '#ced4da' }
        });

        const errorMessages = [];

        validationErrors.forEach((err) => {
            if (err.responseCode === '35') {
                PTPayment.style({ 'cc': { 'border_color': 'red' } });
                errorMessages.push(err.description);
            }

            if (err.responseCode === '43') {
                PTPayment.style({ 'exp': { 'border_color': 'red' } });
                errorMessages.push(err.description);
            }

            if (err.responseCode === '148') {
                PTPayment.style({ 'code': { 'border_color': 'red' } });
                errorMessages.push(err.description);
            }
        });

        if (errorMessages.length > 0) {
            const combinedMessage = errorMessages
                .map((msg, i) => `${i + 1}) ${msg}`)
                .join('<br>');
            this._showError(combinedMessage);
        } else {
            this._getCardToken();
        }
    }

    _getCardToken() {
        PTPayment.process()
            .then((result) => {
                if (result.message) {
                    this._submitPayment(result.message);
                } else {
                    this._showError(this._t('paytrace_shopware6.credit_card.submitError.cardTokenFail'));
                }
            })
            .catch((error) => {
                console.error('Error during payment processing:', error);
                this._showError(this._t('paytrace_shopware6.credit_card.submitError.processError'));
            });
    }

    _vaultedPayment() {
        const selectedCardVaultedId = document.getElementById('saved-cards').value;

        if (!selectedCardVaultedId) {
            this._showError(this._t('paytrace_shopware6.credit_card.submitError.vaultedMissing'));
            return;
        }

        this._showLoading();
        this._submitVaultedPayment(selectedCardVaultedId, this.amount);
    }

    _submitPayment(token) {
        fetch('/capture-paytrace', {
            method: 'POST',
            body: JSON.stringify({ token: token, amount: this.amount }),
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                this._hideLoading();
                if (data.success) {
                    this._hideError();
                    document.getElementById('payTrace-transaction-id').value = data.transactionId;
                    this.confirmOrderForm.submit();
                } else {
                    console.error('Payment failed:', data.message || 'Unknown error');
                    this._showError(this._t('paytrace_shopware6.credit_card.submitError.paymentFailed'));
                }
            })
            .catch(error => {
                this._hideLoading();
                console.error('Payment submission failed:', error);
                this._showError(this._t('paytrace_shopware6.credit_card.submitError.unknown'));
            });
    }

    _submitVaultedPayment(selectedCardVaultedId, amount) {
        fetch('/vaulted-capture-paytrace', {
            method: 'POST',
            body: JSON.stringify({ selectedCardVaultedId, amount: amount }),
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this._hideError();
                    document.getElementById('payTrace-transaction-id').value = data.transactionId;
                    this.confirmOrderForm.submit();
                } else {
                    console.error('Vaulted payment failed:', data.message || 'Unknown error');
                    this._showError(this._t('paytrace_shopware6.credit_card.submitError.vaultedError'));
                }
            })
            .catch(error => {
                console.error('Vaulted payment submission failed:', error);
                this._showError(this._t('paytrace_shopware6.credit_card.submitError.vaultedUnknown'));
            });
    }

    _showError(message) {
        if (this.errorEl) {
            this.errorEl.innerHTML = message;
            this.errorEl.classList.remove('d-none');
        }
        this._enableSubmit();
    }

    _hideError(inputElement = null) {
        if (this.errorEl) {
            this.errorEl.innerHTML = '';
            this.errorEl.classList.add('d-none');
        }

        // Reset border color when the user interacts with the input fields
        if (inputElement) {
            PTPayment.style({
                [inputElement.name]: { 'border_color': '#ced4da' }
            });
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
    _t(key) {
        return window.translation?.[key] || key;
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

}
