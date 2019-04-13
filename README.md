# ScandiPWA_QuoteGraphQl

**QuoteGraphQl** provides basic types and resolvers for Checkout steps.

> ⚠️ This module is WIP

## Endpoint description

### saveCartItem

This endpoint allows to submit items to cart following the default API payload schema. In beneath example is a configurable product option addition to cart.

```graphql
mutation SaveCartItem($cartItem: CartItemInput!) {
    saveCartItem(cartItem: $cartItem) {
        item_id
        name
        price
        product_type
        qty
        quote_id
        sku
    }
}
```

```json
{
	"cartItem": {
		"sku":"n31189077-1",
		"product_type":"configurable",
		"qty":1,
		"quote_id":"s44Xcnya8dmbysAeNTOozFsZCh8tyCH9",
		"product_option":{
			"extension_attributes":{
				"configurable_item_options":[
					{
						"option_id":"93",
						"option_value":75
					},{
						"option_id":"212",
						"option_value":79
						
					}
				]
			}
		}	
	}
}
```

### estimateShippingCosts

```graphql
mutation EstimateShippingCosts(
    $guestCartId: String!
    $address: EstimateShippingCostsAddress!
) {
    estimateShippingCosts(address: $address, guestCartId: $guestCartId) {
        carrier_code
        method_code
        carrier_title
        method_title
        error_message
        amount
        base_amount
        price_excl_tax
        price_incl_tax
        available
    }
}
```

```json
{
  "guestCartId": "s44Xcnya8dmbysAeNTOozFsZCh8tyCH9",
  "address": {
      "region": "New York",
      "region_id": 43,
      "region_code": "NY",
      "country_id": "US",
      "street": [
      	"123 Oak Ave"
      ],
      "postcode": "10577",
      "city": "Purchase",
      "firstname": "Jane",
      "lastname": "Doe",
      "customer_id": 4,
      "email": "jdoe@example.com",
      "telephone": "(512) 555-1111",
      "same_as_billing": 1
  }
}
```

### saveAddressInformation

```graphql
mutation SaveAddressInformation(
  	$addressInformation: SaveAddressInformation!
  	$guestCartId: String
) {
	saveAddressInformation(
		addressInformation: $addressInformation,
    	guestCartId: $guestCartId
  	) {
  		payment_methods {
    		code
    		title
  		}
    	totals {
			grand_total
      		items {
        		name
        		qty
      		}
    	}
	}
}
```

```json
{
   "guestCartId": "s44Xcnya8dmbysAeNTOozFsZCh8tyCH9",
   "addressInformation":{
      "shipping_address":{
         "region":"New York",
         "region_id":43,
         "region_code":"NY",
         "country_id":"US",
         "street":[
            "123 Oak Ave"
         ],
         "postcode":"10577",
         "city":"Purchase",
         "firstname":"Jane",
         "lastname":"Doe",
         "email":"jdoe@example.com",
         "telephone":"512-555-1111"
      },
      "billing_address":{
         "region":"New York",
         "region_id":43,
         "region_code":"NY",
         "country_id":"US",
         "street":[
            "123 Oak Ave"
         ],
         "postcode":"10577",
         "city":"Purchase",
         "firstname":"Jane",
         "lastname":"Doe",
         "email":"jdoe@example.com",
         "telephone":"512-555-1111"
      },
      "shipping_carrier_code":"flatrate",
      "shipping_method_code":"flatrate"
   }
}
```

### savePaymentInformationAndPlaceOrder

```graphql
mutation SavePaymentInformationAndPlaceOrder(
  $paymentInformation: PaymentInformation!,
  $guestCartId: String,
) {
  	savePaymentInformationAndPlaceOrder(
  		paymentInformation: $paymentInformation,
    	guestCartId: $guestCartId
  ) {
  	orderID
  }
}
```

```json
{
  "guestCartId": "s44Xcnya8dmbysAeNTOozFsZCh8tyCH9",
  "paymentInformation": {
    "paymentMethod": {
        "method": "checkmo"
    },
    "billing_address":{
       "region":"New York",
       "region_id":43,
       "region_code":"NY",
       "country_id":"US",
       "street":[
          "123 Oak Ave"
       ],
       "postcode":"10577",
       "city":"Purchase",
       "firstname":"Jane",
       "lastname":"Doe",
       "email":"jdoe@example.com",
       "telephone":"512-555-1111"
    }
  }
}
```