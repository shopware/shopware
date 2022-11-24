import DeviceDetection from 'src/helper/device-detection.helper';

/**
 * @package storefront
 */
describe('device-detection.helper', () => {
    function hasDetected(detectionList, ...truthy) {
        let correctlyDetected = true;

        truthy.forEach((predicate) => {
            if (!detectionList.hasOwnProperty(predicate)) {
                throw new Error(`${predicate} not in detection list`);
            }

            if (detectionList[predicate] === false) {
                correctlyDetected = false;
            }

            delete detectionList[predicate];
        });

        Object.keys(detectionList).forEach((key) => {
            if (detectionList[key] === true) {
                correctlyDetected = false;
            }
        });

        return correctlyDetected;
    }

    function setUserAgent(agent) {
        navigator.__defineGetter__('userAgent', function() {
            return agent;
        })
    }

    const originalUserAgent = navigator.userAgent;

    afterEach(() => {
        navigator.__defineGetter__('userAgent', function() {
            return originalUserAgent;
        });
    });

    test('it detects nothing', () => {
        setUserAgent('im a creepy agent');
        const detectionList = DeviceDetection.getList();

        expect(hasDetected(detectionList)).toBe(true);
    });

    test('it detects internet explorer', () => {
        setUserAgent('Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko');
        const detectionList = DeviceDetection.getList();

        expect(hasDetected(detectionList, 'is-native-windows', 'is-ie')).toBe(true);
    });

    test('it detects edge', () => {
        setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML like Gecko) Chrome/46.0.2486.0 Safari/537.36 Edge/13.10586');
        const detectionList = DeviceDetection.getList();

        expect(hasDetected(detectionList, 'is-native-windows', 'is-edge')).toBe(true);
    });

    test('it detects iphone', () => {
        setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_3 like Mac OS X) AppleWebKit/603.3.8 (KHTML, like Gecko) Version/10.0 Mobile/14G60 Safari/602.1');
        const detectionList = DeviceDetection.getList();

        expect(hasDetected(detectionList, 'is-ios', 'is-iphone')).toBe(true);
    });

    test('it detects ipad', () => {
        setUserAgent('Mozilla/5.0(iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10');
        const detectionList = DeviceDetection.getList();

        expect(hasDetected(detectionList, 'is-ios', 'is-iphone', 'is-ipad')).toBe(true);
    });
});
