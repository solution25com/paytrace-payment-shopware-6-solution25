import './component/paytrace-api-test';

import PaytraceApiTestService from './service/paytrace-api-test-service';

Shopware.Service().register('paytraceApiTestService', () => {
    return new PaytraceApiTestService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});