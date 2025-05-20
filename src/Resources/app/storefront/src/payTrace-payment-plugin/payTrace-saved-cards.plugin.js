export default class PayTraceSavedCardsPlugin extends window.PluginBaseClass {
  static options = {
    paymentToken: '',
  };

  init() {
    this.options.paymentToken = this.el.getAttribute('data-client-key');
    this._registerElements();
    this._bindEvents();
    this._setupPayTrace();
  }

  _registerElements() {
    this.toggleBtn = this.el.querySelector('#toggleAddCardForm');
    this.formContainer = this.el.querySelector('#addCardFormContainer');
    this.protectFormButton = this.el.querySelector('#ProtectForm');
    this.errorContainer = this.el.querySelector('#errorContainer');
  }

  _bindEvents() {
    if (this.toggleBtn && this.formContainer) {
      this.toggleBtn.addEventListener('click', () => {
        const isHidden = this.formContainer.style.display === 'none';
        this.formContainer.style.display = isHidden ? 'block' : 'none';
        this.toggleBtn.textContent = isHidden ? this._t('paytrace_shopware6.savedCards.js.toggle_hide_form') : this._t('paytrace_shopware6.savedCards.js.toggle_show_form');
      });
    }

    if (this.protectFormButton) {
      this.protectFormButton.addEventListener('click', this._handleFormSubmit.bind(this));
    }

    this.el.addEventListener('click', async (event) => {
      if (event.target.closest('.delete-card-btn')) {
        try {
          await this._handleDeleteCard(event);
        } catch (error) {
          console.error('Error handling delete card:', error);
          this._showError([this._t('paytrace_shopware6.savedCards.js.error_delete_failed')]);
        }
      }
    });
  }

  _validateBillingData() {
    const requiredFields = [
      {id: 'nameSurname', name: 'Name'},
      {id: 'streetAddress', name: 'Street Address'},
      {id: 'city', name: 'City'},
      {id: 'state', name: 'State'},
      {id: 'postalCode', name: 'Postal Code'},
      {id: 'country', name: 'Country'}
    ];

    const errors = [];

    for (const field of requiredFields) {
      const el = this.el.querySelector(`#${field.id}`);
      if (!el || !el.value.trim()) {
        errors.push(this._t('paytrace_shopware6.savedCards.js.error_required_field').replace('{field}', field.name));
      }
    }

    if (errors.length > 0) {
      this._showError(errors);
      return false;
    }

    return true;
  }

  _showError(errors) {
    if (!this.errorContainer) return;

    this.errorContainer.innerHTML = '';
    this.errorContainer.style.display = 'block';

    errors.forEach(err => {
      const errorElement = document.createElement('div');
      errorElement.classList.add('error-message');
      errorElement.textContent = err;
      this.errorContainer.appendChild(errorElement);
    });
  }

  _clearError() {
    if (this.errorContainer) {
      this.errorContainer.innerHTML = '';
      this.errorContainer.style.display = 'none';
    }
  }

  _setupPayTrace() {
    PTPayment.setup({
      authorization: { clientKey: this.options.paymentToken },
      styles: {
        cc: {
          background_color: '#ffffff',
          border_style: 'solid',
          border_color: '#ced4da',
          input_border_radius: '6px',
          font_color: '#495057',
          font_size: '14px',
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
          border_style: 'solid',
          border_color: '#ced4da',
          input_border_radius: '6px',
          font_color: '#495057',
          font_size: '14px',
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
          border_style: 'solid',
          border_color: '#ced4da',
          input_border_radius: '6px',
          font_color: '#495057',
          font_size: '14px',
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
          type: 'dropdown',
        },
        body: {
          background_color: '#ffffff'
        },
      }
    }).then(() => {
      PTPayment.theme('label-extended-top');
    }).catch(error => {
      console.error('Error during PayTrace setup:', error);
    });
  }

  async _handleFormSubmit() {
    if (!this._validateBillingData()) return;
    this._showLoading();

    const getValue = id => this.el.querySelector(`#${id}`)?.value || '';
    const streetAddress2 = getValue('streetAddress2');

    const billingData = {
      name: getValue('nameSurname'),
      street_address: getValue('streetAddress'),
      ...(streetAddress2 && {street_address2: streetAddress2}),
      city: getValue('city'),
      state: getValue('state'),
      postal_code: getValue('postalCode'),
      country: getValue('country')
    };

    try {
      const result = await PTPayment.process();

      if (!result.message) {
        console.error('Failed to receive a card token');
        this._hideLoading();
        return;
      }

      const response = await fetch('/account/payTrace-saved-cards/add-card', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          cardToken: result.message,
          billing_address: billingData
        }),
      });

      const res = await response.json();
      if (res.error) {
        console.error('Error from backend:', res.message);
        this._hideLoading();
      } else {
        this._hideLoading();
        location.reload();
      }
    } catch (error) {
      console.error('Error during payment processing:', error);
      this._hideLoading();
    }
  }

  async _handleDeleteCard(event) {
    const button = event.target.closest('.delete-card-btn');
    if (!button) return;

    const cardId = button.getAttribute('data-card-id');

    const userConfirmed = confirm(this._t('paytrace_shopware6.savedCards.js.error_confirm_delete'));
    if (!userConfirmed) return;

    this._showDeleteLoading(button);

    try {
      const response = await fetch(`/account/payTrace-saved-cards/delete-card/${cardId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cardId }),
      });

      const res = await response.json();
      if (res.error) {
        console.error('Error:', res.message);
        this._showError([res.message || this._t('paytrace_shopware6.savedCards.js.error_delete_failed')]);
      } else {
        button.closest('.saved-card')?.remove();
      }
    } catch (err) {
      console.error('Error during delete request:', err);
      this._showError([this._t('paytrace_shopware6.savedCards.js.error_delete_failed')]);
    }
    finally {
      this._hideDeleteLoading(button);
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

  _showDeleteLoading(button) {
    const loader = button.closest('.saved-card').querySelector('.delete-loading-indicator');
    if (loader) loader.classList.remove('d-none');
  }

  _hideDeleteLoading(button) {
    const loader = button.closest('.saved-card').querySelector('.delete-loading-indicator');
    if (loader) loader.classList.add('d-none');
  }


}
