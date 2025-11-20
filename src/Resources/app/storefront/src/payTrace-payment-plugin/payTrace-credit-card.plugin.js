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
    this.saveCardCheckbox = document.getElementById('save-paytrace-card');
    this.jsDataEl = document.getElementById('paytrace-jsData');
    this.translations = this.jsDataEl ? JSON.parse(this.jsDataEl.dataset.jsdata).translations : {};
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

    const newCardOption = document.createElement('option');
    newCardOption.value = '__new__';
    newCardOption.textContent = this._t('optionUseNewCard');
    dropdown.appendChild(newCardOption);

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
      }, authorization: {
        clientKey: this.clientKey
      }
    })
      .then(() => {
        PTPayment.theme('label-extended-top')
        console.warn('PayTrace setup complete');
      })
      .catch((error) => {
        console.error('Error during PayTrace setup:', error);
        this._showError(this._t('submitErrorInitFail'));
      });
  }

  _registerEvents() {
    this.confirmOrderForm.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopPropagation();

      this._hideError();
      this._disableSubmit();
      this._showLoading();

      const savedCardSelected = document.getElementById('saved-cards')?.value;

      if (savedCardSelected && savedCardSelected !== '__new__') {
        this._vaultedPayment();
      } else {
        PTPayment.validate((validationErrors) => {
          if (validationErrors.length > 0) {
            this._handleValidationErrors(validationErrors);
            this._hideLoading();
            return;
          }

          this._getCardToken();
        });
      }
    });

    const dropdown = document.getElementById('saved-cards');
    const newCardSection = document.getElementById('new-card-section');

    if (dropdown && newCardSection) {
      dropdown.addEventListener('change', (e) => {
        if (e.target.value === '__new__') {
          newCardSection.style.display = 'block';
        } else {
          newCardSection.style.display = 'none';
        }

        this._hideError();
      });

      const event = new Event('change');
      dropdown.dispatchEvent(event);

    }

    const inputs = this.parentCreditCardWrapper.querySelectorAll('input, select');
    inputs.forEach(input => {
      input.addEventListener('input', () => this._hideError(input));
    });
  }

  _handleValidationErrors(validationErrors) {
    PTPayment.style({
      'cc': {'border_color': '#ced4da'},
      'exp': {'border_color': '#ced4da'},
      'code': {'border_color': '#ced4da'}
    });

    const errorMessages = [];

    validationErrors.forEach((err) => {
      if (err.responseCode === '35') {
        PTPayment.style({'cc': {'border_color': 'red'}});
        errorMessages.push(err.description);
      }

      if (err.responseCode === '43') {
        PTPayment.style({'exp': {'border_color': 'red'}});
        errorMessages.push(err.description);
      }

      if (err.responseCode === '148') {
        PTPayment.style({'code': {'border_color': 'red'}});
        errorMessages.push(err.description);
      }
    });

    if (errorMessages.length > 0) {
      const combinedMessage = errorMessages
        .map((msg, i) => `${i + 1}) ${msg}`)
        .join('<br>');
      this._showError(combinedMessage);
      this._hideLoading();
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
          this._showError(this._t('submitErrorCardTokenFail'));
          this._hideLoading();
        }
      })
      .catch((error) => {
        console.error('Error during payment processing:', error);
        this._showError(this._t('submitErrorProcessError'));
        this._hideLoading();
      });
  }

  _vaultedPayment() {
    const selectedCardVaultedId = document.getElementById('saved-cards').value;

    if (!selectedCardVaultedId) {
      this._showError(this._t('submitErrorVaultedMissing'));
      this._hideLoading();
      return;
    }

    this._showLoading();
    this._submitVaultedPayment(selectedCardVaultedId, this.amount);
  }

  _showNote() {
    document.getElementById('paytrace-card-note-message').classList.remove('d-none');
  }

  _submitPayment(token) {
    const flow = document.getElementById('payTrace_payment').getAttribute('data-flow');

    if (flow === 'order_payment') {
      const body = {
        token: token,
        amount: this.amount,
        saveCard: this.saveCardCheckbox?.checked || false
      }

      document.getElementById('paytracePaymentData').value = JSON.stringify(body);
      document.getElementById('confirmOrderForm').submit();
    } else {
      fetch('/capture-paytrace', {
        method: 'POST',
        body: JSON.stringify({
          token: token,
          amount: this.amount,
          saveCard: this.saveCardCheckbox?.checked || false
        }),
        headers: {'Content-Type': 'application/json'}
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
              this._showError(data.message || this._t('submitErrorPaymentFailed'));
              this._showNote()
              this._hideLoading();
            }
          })
          .catch(error => {
            this._hideLoading();
            console.error('Payment submission failed:', error);
            this._showError(this._t('submitErrorUnknown'));
          });
    }
  }

  _submitVaultedPayment(selectedCardVaultedId, amount) {
    const flow = document.getElementById('payTrace_payment').getAttribute('data-flow');

    if (flow === 'order_payment') {
      const body = {
        selectedCardVaultedId,
        amount: amount
      }

      document.getElementById('paytracePaymentData').value = JSON.stringify(body);
      document.getElementById('confirmOrderForm').submit();
    } else {
      fetch('/vaulted-capture-paytrace', {
        method: 'POST',
        body: JSON.stringify({selectedCardVaultedId, amount: amount}),
        headers: {'Content-Type': 'application/json'}
      })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              this._hideError();
              document.getElementById('payTrace-transaction-id').value = data.transactionId;
              this.confirmOrderForm.submit();
            } else {
              console.error('Vaulted payment failed:', data.message || 'Unknown error');
              this._showError(data.message || this._t('submitErrorVaultedError'));
              this._hideLoading();
            }
          })
          .catch(error => {
            console.error('Vaulted payment submission failed:', error);
            this._showError(this._t('submitErrorVaultedUnknown'));
            this._hideLoading();
          });
    }
  }

  _showError(message) {
    if (this.errorEl) {
      this.errorEl.innerHTML = message;
      this.errorEl.classList.remove('d-none');
    }
    this._enableSubmit();
    this._hideLoading();
  }

  _hideError(inputElement = null) {
    if (this.errorEl) {
      this.errorEl.innerHTML = '';
      this.errorEl.classList.add('d-none');
    }

    // Reset border color when the user interacts with the input fields
    if (inputElement) {
      PTPayment.style({
        [inputElement.name]: {'border_color': '#ced4da'}
      });
    } else {
      PTPayment.style({
        'cc': {'border_color': '#ced4da'},
        'exp': {'border_color': '#ced4da'},
        'code': {'border_color': '#ced4da'}
      });
    }
    document.getElementById('paytrace-card-note-message').classList.add('d-none');
    this._enableSubmit();
  }

  _disableSubmit() {
    const confirmButton = this.confirmOrderForm.querySelector('button[type="submit"]');
    if (confirmButton) {
      confirmButton.disabled = true;
    }
    confirmButton.classList.add('is-loading');

    const savedCardsDropdown = document.getElementById('saved-cards');
    if (savedCardsDropdown) {
      savedCardsDropdown.disabled = true;
    }
  }

  _enableSubmit() {
    const confirmButton = this.confirmOrderForm.querySelector('button[type="submit"]');
    if (confirmButton) {
      confirmButton.disabled = false;
    }

    const savedCardsDropdown = document.getElementById('saved-cards');
    if (savedCardsDropdown) {
      savedCardsDropdown.disabled = false;
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
    this._disableSaveCardCheckbox();
  }

  _hideLoading() {
    const loader = document.getElementById('paytrace-loading-indicator');
    if (loader) {
      loader.classList.add('d-none');
    }
    this._enableSaveCardCheckbox();
  }

  _disableSaveCardCheckbox() {
    if (this.saveCardCheckbox) {
      this.saveCardCheckbox.disabled = true;
    }
  }

  _enableSaveCardCheckbox() {
    if (this.saveCardCheckbox) {
      this.saveCardCheckbox.disabled = false;
    }
  }
}
