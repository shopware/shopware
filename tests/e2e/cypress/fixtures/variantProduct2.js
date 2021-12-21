export default {
    "id": "3ecc7075aaad49c69c013cb1e58bfc4e",
    "name": "Second test base product",
    "productNumber": "TEST2",
    "price": [
        {
            "gross": 111,
            "net": 111,
            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
            "linked": false
        }
    ],
    "shippingFree": true,
    "stock": 1234,
    "tax": {
        "taxRate": 0,
        "name": "foo"
    },
    "properties": [
        {
            "id": "3ecc7075aaad49c69c013cb1e58bfc4e",
            "name": "X",
            "group": {
                "id": "3ecc7075aaad49c69c013cb1e58bfc4e",
                "name": "size",
                "displayType": "text"
            }
        },
        {
            "id": "98a3f7d70c4542cbaee991ed16913ef8",
            "name": "L",
            "groupId": "3ecc7075aaad49c69c013cb1e58bfc4e"
        },
        {
            "id": "10d1d7046df74cfe90765b93e13acb47",
            "name": "M",
            "groupId": "3ecc7075aaad49c69c013cb1e58bfc4e"
        }
    ],
    "children": [
        {
            "productNumber": "TEST2.1",
            "stock": 10,
            "options": [
                {"id": "3ecc7075aaad49c69c013cb1e58bfc4e"},
                {"id": "f1d2554b0ce847cd82f3ac9bd1c0dfba"}
            ]
        },
        {
            "productNumber": "TEST2.2",
            "stock": 10,
            "options": [
                {"id": "3ecc7075aaad49c69c013cb1e58bfc4e"}
            ]
        },
        {
            "productNumber": "TEST2.3",
            "stock": 10,
            "options": [
                {"id": "10d1d7046df74cfe90765b93e13acb47"}
            ]
        }
    ],
    "configuratorSettings": [
        {
            "id": "f4fe600c00e64da4941726183dc1da82",
            "optionId": "3ecc7075aaad49c69c013cb1e58bfc4e"
        },
        {
            "id": "f4fe600c00e64da4941726183dc2da83",
            "optionId": "f1d2554b0ce847cd82f3ac9bd1c0dfba"
        },
        {
            "id": "39efd9cadee44eb8a63fa3c211b823a5",
            "optionId": "98a3f7d70c4542cbaee991ed16913ef8"
        },
        {
            "id": "45d8e29ced0f49e183abb1046f404188",
            "optionId": "10d1d7046df74cfe90765b93e13acb47"
        }
    ]
}
