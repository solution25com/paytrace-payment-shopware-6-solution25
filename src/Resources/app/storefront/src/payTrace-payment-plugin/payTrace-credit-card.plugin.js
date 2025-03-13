import Plugin from 'src/plugin-system/plugin.class';

export default class PayTraceCreditCardPlugin extends Plugin {

    init() {
        PTPayment.setup({
            authorization: {
                clientKey: ''
            }
        }).then(function (instance) {
            console.log('PayTrace setup complete');
        }).catch(function (error) {
            console.error('Error during PayTrace setup:', error);
        });

        document.getElementById("ProtectForm").addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();

            this.getCardToken();
        });
    }

    getCardToken() {
        PTPayment.process()
            .then((result) => {
                console.log('Token received:', result);
                if (result.token) {
                    // Token successfully received
                    this.submitPayment(result.token);
                } else {
                    console.error('Failed to receive a token:', result);
                }
            })
            .catch((error) => {
                console.error('Error during payment processing:', error);
                this.handleError(error);
            });
    }

    handleError(error) {
        console.error('Error during card processing:', error);
    }

    submitPayment(token) {
        console.log('Submitting payment with token:', token);
        // Example:
        // fetch('/payment-api', {
        //     method: 'POST',
        //     body: JSON.stringify({ token: token }),
        //     headers: { 'Content-Type': 'application/json' }
        // })
        // .then(response => response.json())
        // .then(data => console.log(data))
        // .catch(error => console.error('Payment submission failed:', error));
    }
}
