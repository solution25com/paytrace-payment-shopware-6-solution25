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
        this.errorEl = document.getElementById('error-message');
        this.myTest = document.getElementById('nameSurname');
        this.parentCreditCardWrapper.querySelectorAll('.paytrace-form-container input');
    }

    init() {
        this._registerElements();
        this._populateDropdown();
        this._setupPayTrace();
        this._bindEvents();
    }

    _populateDropdown() {
        const cards = JSON.parse(this.cardsDropdown);
        const dropdown = document.getElementById('saved-cards');

        dropdown.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select a saved card --';
        dropdown.appendChild(defaultOption);

        cards.forEach(card => {
            const option = document.createElement('option');
            option.value = card.vaultedCustomerId;
            option.textContent = card.customerLabel;
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
                    height: '32px',
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
                    height: '32px',
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
                    height: '32px',
                    width: '100%',
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
            .then(() => {})
            .catch((error) => {
                console.error('Error during PayTrace setup:', error);
                this._showError('Failed to initialize payment system.');
            });
    }

    _bindEvents() {
        document.getElementById("ProtectForm").addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            this._getCardToken();
        });

        document.getElementById("SelectCardButton").addEventListener("click", (e) => {
            e.preventDefault();
            this._vaultedPayment();
        }, { once: true });

        document.getElementById('saved-cards').addEventListener('change', (e) => {
            const selectedCard = e.target.value;
            const selectCardButton = document.getElementById('SelectCardButton');

            if (selectedCard) {
                selectCardButton.style.display = 'block';
            } else {
                selectCardButton.style.display = 'none';
            }
        });
    }

    _getCardToken() {
        PTPayment.process()
            .then((result) => {
                if (result.message) {
                    this._submitPayment(result.message);
                } else {
                    this._showError('Failed to receive card token.');
                }
            })
            .catch((error) => {
                console.error('Error during payment processing:', error);
                this._showError('An error occurred during card processing.');
            });
    }

    _vaultedPayment() {
        const selectedCardVaultedId = document.getElementById('saved-cards').value;

        if (!selectedCardVaultedId) {
            this._showError('Please select a saved card before proceeding.');
            return;
        }

        this._submitVaultedPayment(selectedCardVaultedId, this.amount);
    }

    _submitPayment(token) {
        const billingAddressData = this._getBillingData();

        fetch('/capture-paytrace', {
            method: 'POST',
            body: JSON.stringify({ token: token, amount: this.amount, billingData: billingAddressData}),
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this._hideError();
                    document.getElementById('payTrace-transaction-id').value = data.transactionId;
                    this.confirmOrderForm.submit();
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
                    console.error('Payment failed:', data.message || 'Unknown error');
                    this._showError(data.message || 'Vaulted card payment failed.');
                }
            })
            .catch(error => {
                console.error('Vaulted payment submission failed:', error);
                this._showError('An unexpected error occurred while using a saved card.');
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

    _getBillingData() {
        return {
            name: document.getElementById('nameSurname').value,
            street: document.getElementById('streetAddress').value,
            street2: document.getElementById('streetAddress2').value,
            city: document.getElementById('city').value,
            state: document.getElementById('state').value,
            zip: document.getElementById('postalCode').value,
            country: document.getElementById('country').value
        };
    }



    _validateBillingData() {
        const inputs = this.parentCreditCardWrapper.querySelectorAll('.paytrace-form-container input');
        for (const input of inputs) {
            if (input.required && !input.value.trim()) {
                this._showError(`${input.name} is required.`);
                input.focus();
                return false;
            }
        }
        return true;
    }

}
