const ApiService = Shopware.Classes.ApiService;

export default class PaytraceApiTestService extends ApiService {

    constructor(httpClient, loginService, apiEndpoint = 'paytrace-test-connection') {
        super(httpClient, loginService, apiEndpoint);
    }
    check(values) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/test-connection`, values, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}