# Changelog

All notable changes to this project will be documented in this file.


## [1.1.0] - 2025-09-01
### Bug Fixes
- **Saved Card Dashboard**  
  Fixed an issue where saved cards were not displaying correctly in the user dashboard.  

- **Checkout Loader**  
  Resolved a problem where the loader on the checkout submit button was not behaving as expected.  

- **ACH Payment Fields**  
  Locked ACH payment fields after order submission to prevent changes during processing.  

- **Duplicate Pay Buttons**  
  Removed duplicate pay buttons when paying with saved cards.  

### Features
- **API Test Button**  
  Added a new API test button in plugin configuration for easier connectivity checks.  

### Improvements
- **Twig Templates**  
  Various adjustments and fixes in Twig templates for better stability and rendering.  

---

## [1.0.14] - 2025-06-20
### Bug Fixes
- **Payment Failure Resolution**  
  Fixed an issue where transactions were failing intermittently due to incorrect request formatting. Payments now process reliably.

---

## [1.0.13] - 2025-05-30
### Enhancements
- **Save Card Checkbox**  
  Added a checkbox option to the credit card payment form allowing customers to choose whether to save their card for future purchases.

### Technical Fixes
- **SalesChannelContext Argument Order**  
  Reordered method arguments to ensure `SalesChannelContext` is passed as the last parameter, aligning with Shopware best practices and improving compatibility.

- **Payment Method Form Visibility Fix**  
  Fixed an issue where the payment form was not appearing due to reliance on a static `formattedHandlerIdentifier` name. The form now displays correctly based on the dynamically resolved payment handler.

---

## [1.0.12] - 2025-05-23
### Enhancements
- **Transaction Type Configuration Dropdown**  
  Added a dropdown menu in the administration settings to allow merchants to easily configure the transaction type for PayTrace.

### Technical Fixes
- **Refund Status Reversion Logic**  
  Implemented logic to automatically revert the order status from "Refunded" back to "Paid" when a refund attempt fails.  
  Additionally, administrators will now see a clear error message explaining why the refund could not be processed.

---

## [1.0.11] - 2025-05-20
### Enhancements
- **Loading Indicators Added**  
  Introduced visual loaders for ACH, Credit Card, and Saved Card payment forms to enhance user experience and provide clearer feedback during processing.

### Technical Fixes
- **ACH Status Handling Improvements**  
  Refined status processing logic for ACH payments to ensure consistent and accurate state transitions in the checkout and order flow.

---

## [1.0.10] - 2025-05-20
### Enhancements
- **Improved Snippet Management**  
  All user-facing texts have been moved to snippet files for easier translation and customization via the Shopware Admin.

- **Separated Styles from Twig Templates**  
  SCSS/CSS styles have been decoupled from Twig templates and placed in dedicated style files. This improves maintainability and aligns with Shopware’s best frontend practices.

### Technical Fixes
- **PHPStan level 8 error fixes**  
  Adjustments were made to ensure compatibility with PHPStan level 8.

---

## [1.0.9] - 2025-05-16
### Enhancements
- **Renamed "Saved Cards" Menu on Account Page**  
  The "Saved Cards" menu under the customer account section has been renamed to **"PayTrace Saved Cards"** for clearer branding and easier user navigation.

### Technical Improvements
- **Code Review Adjustments**  
  Refactored various parts of the codebase to address findings from an internal code review. These changes improve overall maintainability, readability, and adherence to Shopware 6 best practices.

---

## [1.0.8] - 2025-05-13
### Enhancements
- **Show Last Four Digits of Card**  
  The last four digits of the credit card are now displayed during checkout for saved payment methods, enhancing transparency and user confidence.

### UI Improvements
- **Removed "Pay" Button for ACH Payment Method**  
  Streamlined the checkout flow by conditionally removing the redundant "Pay" button when ACH is selected, ensuring a smoother user experience.

### Technical Fixes
- **Extension Verifier Compliance (Level 8)**  
  Addressed issues flagged by the Extension Verifier (Level 8), ensuring full compliance with Shopware's extension quality standards.

---

## [1.0.7] - 2025-05-08
### New Features
- **Customer Vault Endpoint Integration**  
  Added a secure API endpoint to manage customer vault operations, enabling seamless tokenization and retrieval of saved payment methods.

- **Dropdown Selection in Checkout**  
  Introduced a dynamic dropdown component in the checkout process for selecting saved payment methods, enhancing user experience and simplifying card reuse.

---

## [1.0.6] - 2025-05-07
### Enhancements
- Improved configuration handling by adding a descriptive error message when a required config file or environment variable is missing. This prevents silent failures and aids in quicker debugging.

### Bug Fixes
- Fixed an issue where the application would fail silently if the configuration file was not found.
- Minor stability and performance improvements.

### Error Handling Update
If the required configuration is missing, the following error message will now be displayed:

`[Configuration Error] Required configuration file or environment variable is missing. Please check your plugin settings.`

---

## [1.0.5] - 2025-04-30
## [1.0.1] - 2025-04-17
## [1.0.0] - 2025-04-14


---
