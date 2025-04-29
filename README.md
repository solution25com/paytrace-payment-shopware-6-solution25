![paytrace](https://github.com/user-attachments/assets/58d1ee9f-560d-43cf-8b47-c7692034a335)
# PayTrace Payment for Shopware 6

## Introduction

The **PayTrace Payment Plugin** enables secure and efficient credit card and ACH transactions directly within your Shopware 6 store. With advanced tokenization, fraud protection, and full admin control, PayTrace helps you boost conversions and process payments with confidence.

This plugin supports one-time and saved-card transactions, integrates directly into the checkout, and provides merchants with detailed transaction visibility and management.

---

##  Key Features

###  Secure Payment Processing
- Accept **Visa**, **MasterCard**, **AMEX**, **Discover**, and **ACH** transactions.

###  Tokenization
- Securely stores customer payment information for faster future checkouts.

###  Saved Cards
- Enable customers to reuse previously saved cards for quick repeat purchases.

###  Admin Panel Integration
- Configure API credentials, transaction settings, and view payment logs within the Shopware admin.

###  Full Transaction Lifecycle
- Support for **payments**, **refunds**, and **voids**, with logs available in order details.

###  Multi-Currency Support
- Accept payments in various currencies for international reach.

###  PCI DSS Compliance
- Meets strict security standards for payment processing and customer data.

###  Flexible Configuration
- Choose between sandbox or live PayTrace environments. Enable/disable specific payment types per Sales Channel.

###  Mobile & Desktop Optimized
- Responsive and smooth checkout experience across all devices.

###  Real-Time Status Updates
- Payment status automatically reflected in Shopware orders.

---

##  Get Started

### Installation & Activation :

## GitHub

1. Clone the plugin into your Shopware plugins directory:
```bash
git clone https://github.com/solution25com/paytrace-payment-shopware-6-solution25.git
```
## Packagist
 ```
  composer require solution25/pay-trace
  ```

2. **Install the Plugin in Shopware 6**

- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the newly cloned plugin and click Install.

3. **Activate the Plugin**

- After installation, click Activate to enable the plugin.
- In your Shopware Admin, go to Settings > System > Plugins.
- Upload or install the “PayTrace” plugin.
- Once installed, toggle the plugin to activate it.

4. **Verify Installation**

- After activation, you will see PayTrace in the list of installed plugins.
- The plugin name, version, and installation date should appear.

## Plugin Configuration

After installing the plugin, you can configure your **PayTrace** credentials and options through the Shopware Administration panel.

### Accessing the Configuration

1. Go to **Settings > Extensions > PayTrace**
2. Select the **Sales Channel** you want to configure
3. Set the following fields:

### General Settings

![Screenshot from 2025-04-15 15-38-50](https://github.com/user-attachments/assets/3ba2ecce-6644-4350-8896-66c5ce219f7b)

### API Credentials

#### Production

![Screenshot from 2025-04-15 15-39-44](https://github.com/user-attachments/assets/84fa1fb5-d739-4908-9416-7261051ddabb)
- Your PayTrace production client ID
- Your PayTrace production client secret


#### Sandbox

![Screenshot from 2025-04-15 15-39-59](https://github.com/user-attachments/assets/ece709fc-3f35-4d47-b60a-f92ae0fc2ebb)
- Your PayTrace sandbox client ID
- Your PayTrace sandbox client secret

### Additional Settings

![Screenshot from 2025-04-15 15-40-59](https://github.com/user-attachments/assets/386cb784-efa0-4d44-bce0-c21eec62aba0)

- If enabled, payments are captured immediately after authorization (applies to credit card transactions)



## Checkout Experience

The plugin integrates seamlessly into the Shopware 6 checkout, offering a smooth and intuitive payment process. Customers can choose between **Credit Card** and **ACH (eCheck)** payment methods provided by PayTrace, directly on the checkout page.

### ACH (eCheck) Payment

<img width="1330" alt="image" src="https://github.com/user-attachments/assets/2f302c33-c8ae-4066-a940-4f54984137db" />

- Customers simply enter their **Full Name**, **Routing Number**, and **Account Number**.
- The interface automatically updates the payment amount on the button.
- Fully responsive and styled to match modern checkout flows.

## Add a New Card

The plugin supports storing and managing saved credit cards for faster and more convenient checkouts.

### Accessing the Saved Cards

Users can manage their saved payment methods through their **Account Dashboard** by navigating to:

`PayTrace Saved Cards`

### Adding a New Card

Click **"PayTrace Saved Cards"** to open the card form. You will be prompted to enter:

<img width="1466" alt="image(3)" src="https://github.com/user-attachments/assets/93f7a38b-f6dc-482e-8dc1-772e6b29a171" />


<img width="1469" alt="image(2)" src="https://github.com/user-attachments/assets/760c3a0c-a4ee-424b-bb0e-c390e4486d28" />



