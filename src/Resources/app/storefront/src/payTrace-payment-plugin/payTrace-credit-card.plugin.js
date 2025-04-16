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
            styles: {},
            authorization: {
                clientKey: this.clientKey
            }
        })
            .then(() => {
                console.log('PayTrace setup complete');
            })
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
        fetch('/capture-paytrace', {
            method: 'POST',
            body: JSON.stringify({ token: token, amount: this.amount }),
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
}
