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
        this.cardsDropdown = this.parentCreditCardWrapper.getAttribute('data-cardsDropdown');
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
                console.log('Token received:', result);
                if (result.message) {
                    this._submitPayment(result.message);
                } else {
                    console.error('Failed to receive a token:', result);
                }
            })
            .catch((error) => {
                console.error('Error during payment processing:', error);
            });
    }

    _vaultedPayment() {
        const selectedCardVaultedId = document.getElementById('saved-cards').value;
        const amount = this.amount;

        if (!selectedCardVaultedId) {
            alert('No card');
            return;
        }

        this._submitVaultedPayment(selectedCardVaultedId, amount);
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

    _submitVaultedPayment(selectedCardVaultedId, amount) {
        console.log('_submitVaultedPayment')
        fetch('/vaulted-capture-paytrace', {
            method: 'POST',
            body: JSON.stringify({ selectedCardVaultedId, amount: amount }),
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
}
