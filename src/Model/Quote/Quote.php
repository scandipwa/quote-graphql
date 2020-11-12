<?php
/**
 * ScandiPWA - Progressive Web App for Magento
 *
 * Copyright © Scandiweb, Inc. All rights reserved.
 * See LICENSE for license details.
 *
 * @license OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * @package scandipwa/quote-graphql
 * @link    https://github.com/scandipwa/quote-graphql
 */
namespace ScandiPWA\QuoteGraphQl\Model\Quote;

use Magento\Quote\Model\Quote as SourceQuote;

/**
 * Class Quote
 * @package ScandiPWA\QuoteGraphQl\Model\Quote
 */
class Quote extends SourceQuote
{
    /**
     * @return $this|Quote
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $customerIsGuest = !$this->_customer || $this->getCustomerId() == null;
        $this->setCustomerIsGuest($customerIsGuest);
        return $this;
    }
}
