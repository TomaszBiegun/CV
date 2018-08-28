<?php

namespace App\Services\Sntc;

use App\Models\Senetic\Order\Order;
use App\Models\Senetic\Order\Data;
use App\Models\Senetic\Order\GncOrder;
use App\Models\Senetic\Order\GncProduct;
use App\Models\Senetic\Order\Product;
use App\Models\Senetic\Order\PaymentLink;
use App\Models\Senetic\UnifiedPid;
use App\Models\Senetic\SntcOrderNumbers;
use App\Models\Senetic\AxShippingMatrix;

use GetioSDK\CustomerService\Customer\Order\SntcStatus;
use GetioSDK\CustomerService\ProductType;
use GetioSDK\CustomerService\SalesOrder\CspAccount;
use GetioSDK\CustomerService\SalesOrder\Order as SntcOrder;
use GetioSDK\CustomerService\SalesOrder\Data as Cdata;
use GetioSDK\CustomerService\SalesOrder\SaveRequest;
use GetioSDK\CustomerService\SalesOrder\InvoiceAddress;
use GetioSDK\CustomerService\SalesOrder\DeliveryAddress;
use GetioSDK\CustomerService\SalesOrder\EndUserAddress;
use GetioSDK\CustomerService\SalesOrder\AdditionalData;
use DB;
use GetioSDK\CustomerService\SalesOrder\UpdatePaymentLinkRequest;

class SntcOrderService
{
    /**
     * Return array with orders
     *
     * @param  integer $customerId
     * @param  string $domain
     * @param  string $language
     * @param  integer $limit
     * @return array
     */
    public function getOrders($customerId, $domain, $language, $limit, $axCustomerId = 0, $axCompany = '')
    {
        $customerId = intval($customerId);
        $axCustomerId = intval($axCustomerId);

        $builder = Order::where('order_domain', (string) $domain)
            ->where('order_lang', (string) $language);

        if ($axCustomerId > 0 && ! empty($axCompany)) {
            $builder = $builder->where(function ($query) use ($customerId, $axCustomerId, $axCompany) {
                return $query->where('order_customer_id', '=', $customerId)
                    ->orWhere(function ($query) use ($axCustomerId, $axCompany) {
                        return $query->where('order_AxAccountNum', '=', $axCustomerId)
                            ->where('order_AxSalesOrderDataAreaId', '=', $axCompany);
                    });
            });
        } else {
            $builder = $builder->where('order_customer_id', $customerId);
        }

        if ($limit > 0) {
            $builder = $builder->limit((int) $limit);
        }

        return $builder->orderBy('order_id', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Return array with order data
     *
     * @param  integer $customerId
     * @param  string $domain
     * @param  string $language
     * @param  string $number
     * @return array
     */
    public function getOrder($customerId, $domain, $language, $number, $axCustomerId = 0, $axCompany = '')
    {
        $customerId = intval($customerId);
        $axCustomerId = intval($axCustomerId);

        // remove any non digits from string
        $number = filter_var($number, FILTER_SANITIZE_NUMBER_INT);

        $order = Order::where('order_domain', (string) $domain)
            ->where('order_lang', (string) $language)
            ->where('order_number', (string) $number);

        if ($axCustomerId > 0 && ! empty($axCompany)) {
            $order = $order->where(function ($query) use ($customerId, $axCustomerId, $axCompany) {
                return $query->where('order_customer_id', '=', $customerId)
                    ->orWhere(function ($query) use ($axCustomerId, $axCompany) {
                        return $query->where('order_AxAccountNum', '=', $axCustomerId)
                            ->where('order_AxSalesOrderDataAreaId', '=', $axCompany);
                    });
            });
        } else {
            $order = $order->where('order_customer_id', $customerId);
        }

        $order = $order->first();

        if (is_null($order)) {
            return [];
        }

        $order = $order->toArray();

        $products = Product::where('order_id', $order['order_id'])
            ->get()
            ->toArray();

        return [
            'order' => $order,
            'products' => $products,
        ];
    }

    /**
     * Get Order by number
     *
     * @param int $orderNumber
     * @param int $customerId
     *
     * @return Order $order
     */
    public function getOrderByNumber($orderNumber, $customerId)
    {
        return $order = Order::where([
            ['order_number', (int)$orderNumber],
            ['order_customer_id', (int)$customerId]
        ])->first();
    }


    /**
     * Save and return order
     *
     * @param SaveRequest $saveRequest
     *
     * @return SaveRequest|null
     */
    public function saveOrder(SaveRequest $saveRequest)
    {
        $order = $saveRequest->getOrder();
        $data = $saveRequest->getData();
        $invoiceAddress = $saveRequest->getInvoiceAddress();
        $testing = $saveRequest->getTesting();
        $result = $saveRequest->getResult();

        $ecommerceOrderId = $order->getEcommerceOrderId();
        
        // brakuje adresu to koniec zabawy
        if (empty($data) && empty($invoiceAddress)) {
            \Log::critical('(SaveOrder:#'.$ecommerceOrderId.') have empty objects \$data and \$invoiceAddress');
            return null;
        }

        if ($testing) {
            $order->setStatus(999);
        }
        
        

        try {
            $this->defineDlvMode($saveRequest);
            $order = $saveRequest->getOrder();
            
            // Sprawdzenie czy order o takim numerze nie istanie w bazie SNTC - nie powinno raczej być takich wypadkow...
            if ($result['Order'] === false) {
                $this->checkOrderExist($saveRequest);
                $result = $saveRequest->getResult();
            }
            
            // Zostalo to wepchać do bazy i finał
            if ($result['Order'] === false) {
                $this->saveOrderInSntc($saveRequest);
            }

            // Mozna dla pewności dodać resztę danych z klucza
            $getOrder = Order::where('order_ecommerce_order_id','=',$ecommerceOrderId)->first();

            if (empty($getOrder) || empty($getOrder->getId())) {
                throw new \Exception('(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.'Order number not found.');
            }

            $result['Order'] = $getOrder->getId();
            $saveRequest->setResult($result);

            // Dodanie brakujacych danych
            $order->setNumber((int)$getOrder->getNumber());

            // Zapis kopii Ordera
            $this->saveGncOrderInSntc($saveRequest, $getOrder);

            // Dodanie danych cdata
            $this->saveDataInSntc($saveRequest);
            
            // dodanie produktów
            $this->saveProductsInSntc($saveRequest);
            
            // Dodanie linkow platnosci
            $this->savePaymentLink($saveRequest);
            
        } catch (\Exception $e) {
            \Log::critical('(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.': '.$e->getMessage());

            return null;
        }

        return $saveRequest;
    }

    /**
     * Update payment link paid status and date
     *
     * @param UpdatePaymentLinkRequest $updateRequest
     * @return UpdatePaymentLinkRequest
     * @throws \Exception
     */
    public function updatePaymentLink(UpdatePaymentLinkRequest $updateRequest)
    {
        $requestPaymentLink = $updateRequest->getPaymentLink();

        $sntcPaymentLink = PaymentLink::where('senetic_orders_payments_link_hash', $requestPaymentLink->getHash())->first();

        if (! $sntcPaymentLink instanceof PaymentLink) {
            throw new \Exception(PHP_EOL . '(Payment link with hash : '. $requestPaymentLink->getHash() .' not found) Thrown in file: ' . __FILE__ . '#' . __LINE__ . ', method: ' . __METHOD__);
        }

        $sntcPaymentLink->setPaid($requestPaymentLink->getPaid())
            ->setPaidDate($requestPaymentLink->getPaidDate())
            ->save();

        return $updateRequest;
    }

    /**
     * Changes order status in SNTC
     *
     * @param SntcStatus $status
     * @return array
     */
    public function changeSntcOrderStatus(SntcStatus $status)
    {
        $allowedStatuses = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            '10', '11', '12', '13', '14', '51', '52', '53', '54', '55', '56', '666', '999'];

        if (!in_array($status->getStatus(), $allowedStatuses)) {
            return ['error' => 'Not allowed SNTC status'];
        }

        $order = $this->getOrderByNumber($status->getOrderNumber(), $status->getCustomerId());

        try {
            $order->setStatus($status->getStatus());
            $order->save();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return ['status' => 'done'];
    }

    /**
     * compare invoice and delivery address
     *
     * @param InvoiceAddress $invoiceAddress
     * @param DeliveryAddress $deliveryAddress
     *
     * @return bool
     */
    protected function invoiceIsEqDelivery(InvoiceAddress $invoiceAddress, DeliveryAddress $deliveryAddress)
    {
        $invoiceEqDelivery = true;

        if ($invoiceAddress->getCountryId() != $deliveryAddress->getCountryId()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getName() != $deliveryAddress->getName()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getSurname() != $deliveryAddress->getSurname()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getPhonePre() != $deliveryAddress->getPhonePre()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getPhone() != $deliveryAddress->getPhone()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getStreet() != $deliveryAddress->getStreet()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getPostalCode() != $deliveryAddress->getPostalCode()) {
            $invoiceEqDelivery = false;
        }

        if ($invoiceAddress->getCompany() != $deliveryAddress->getCompany()) {
            $invoiceEqDelivery = false;
        }

        return $invoiceEqDelivery;
    }

    /**
     * compare invoice and enduser address
     *
     * @param InvoiceAddress $invoiceAddress
     * @param EndUserAddress $endUserAddress
     *
     * @return bool
     */
    protected function invoiceIsEqEndUser(InvoiceAddress $invoiceAddress, EndUserAddress $endUserAddress)
    {
        $invoiceEqEndUser = true;
        
        if ($this->emptyAddress($invoiceAddress) || $this->emptyAddress($endUserAddress)) {
            return true;
        }

        if ($invoiceAddress->getCountryId() != $endUserAddress->getCountryId()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getName() != $endUserAddress->getName()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getSurname() != $endUserAddress->getSurname()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getPhonePre() != $endUserAddress->getPhonePre()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getPhone() != $endUserAddress->getPhone()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getStreet() != $endUserAddress->getStreet()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getPostalCode() != $endUserAddress->getPostalCode()) {
            $invoiceEqEndUser = false;
        }

        if ($invoiceAddress->getCompany() != $endUserAddress->getCompany()) {
            $invoiceEqEndUser = false;
        }

        return $invoiceEqEndUser;
    }
    
    /**
     * Check if address is empty
     *
     * @param InvoiceAddress|EndUserAddress|DeliveryAddress $address
     *
     * @return bool
     */
    protected function emptyAddress($address)
    {
        if (empty($address->getCountryId())) {
            return true;
        }

        if (empty($address->getName())) {
            return true;
        }

        if (empty($address->getSurname())) {
            return true;
        }

        if (empty($address->getStreet())) {
            return true;
        }

        if (empty($address->getPostalCode())) {
            return true;
        }

        if (empty($address->getPhone())) {
            return true;
        }
        
        return false;
    }

    /**
     * prepare some vars
     *
     * @param DeliveryAddress $deliveryAddress
     * @param EndUserAddress $endUserAddress
     * @param InvoiceAddress $invoiceAddress
     *
     * @return array ['deliveryCompanyOrName','deliveryFullName','endUserFullName','invoiceFullName']
     */
    protected function prepareVars(DeliveryAddress $deliveryAddress, EndUserAddress $endUserAddress, InvoiceAddress $invoiceAddress)
    {
        $deliveryCompanyOrName = $deliveryAddress->getCompany();
        if (empty($deliveryCompanyOrName)) {
            $tmp = [];
            if (! empty($deliveryAddress->getName())) {
                $tmp[] = $deliveryAddress->getName();
            }
            if (! empty($deliveryAddress->getSurname())) {
                $tmp[] = $deliveryAddress->getSurname();
            }
            $deliveryCompanyOrName = implode(' ',$tmp);
        }

        $tmp = [];
        if (! empty($deliveryAddress->getName())) {
            $tmp[] = $deliveryAddress->getName();
        }
        if (! empty($deliveryAddress->getSurname())) {
            $tmp[] = $deliveryAddress->getSurname();
        }
        $deliveryFullName = implode(' ',$tmp);

        $tmp = [];
        if (! empty($endUserAddress->getName())) {
            $tmp[] = $endUserAddress->getName();
        }
        if (! empty($endUserAddress->getSurname())) {
            $tmp[] = $endUserAddress->getSurname();
        }
        $endUserFullName = implode(' ',$tmp);

        $tmp = [];
        if (! empty($invoiceAddress->getName())) {
            $tmp[] = $invoiceAddress->getName();
        }
        if (! empty($invoiceAddress->getSurname())) {
            $tmp[] = $invoiceAddress->getSurname();
        }
        $invoiceFullName = implode(' ',$tmp);

        return [
            'deliveryCompanyOrName' => $deliveryCompanyOrName,
            'deliveryFullName' => $deliveryFullName,
            'endUserFullName' => $endUserFullName,
            'invoiceFullName' => $invoiceFullName,
            ];
    }
    
    /**
     * Check order exist in SNTC
     *
     * @param SaveRequest $saveRequest
     *
     * @return bool
     */
    protected function checkOrderExist(SaveRequest &$saveRequest)
    {
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        $getOrder = Order::where('order_ecommerce_order_id', $ecommerceOrderId)->first();
        
        if (empty($getOrder) || empty($getOrder->getId())) {
            return false;
        }
        
        \Log::info('Sales Order ['.$ecommerceOrderId.'] exist, on Id ['.$getOrder->getId().'] and Number ['.$getOrder->getNumber().'].');
        
        $this->fillMissingResults($getOrder, $saveRequest);
        
        return true;
    }
    
    /**
     * Fill missing values in result$order
     * 
     * @param Order $gncOrder
     * @param SaveRequest $saveRequest
     * 
     * @return void
     */
    protected function fillMissingResults(Order $gncOrder, SaveRequest &$saveRequest)
    {
        $result = $saveRequest->getResult();
        $products = $saveRequest->getProducts();
        $paymentLink = $saveRequest->getPaymentLink();
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        
        $result['Order'] = $gncOrder->getId();
        
        if ($result['GncOrder'] === false) {
            try {
                $getGncOrder = GncOrder::where('order_ecommerce_order_id', $ecommerceOrderId)->first();
            } catch(\Exception $e) {
                throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
            }
            
            if (! empty($getGncOrder) && ! empty($getGncOrder->getId()) ) {
                $result['GncOrder'] = $getGncOrder->getId();
            }
        }
        
        if ($result['Data'] === false) {
            $getData = Data::where('order_id', $gncOrder->getId())->first();
            
            if (! empty($getData) && ! empty($getData->getId())) {
                $result['Data'] = $getData->getId();
            }
        }
        
        if (! empty($products)) {
            foreach ($products as $k=>$product) {
                if (empty($result['Products'][$k])) {
                    try {
                        $getProducts = Product::where('order_id', $gncOrder->getId())
                            ->where('product_part_number', $product->getProductPartNumber())
                            ->firstOrFail();
                    } catch(\Exception $e) {
                        throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                    }
                    if (! empty($getProducts) && ! empty($getProducts->getId())) {
                        $result['Products'][$k] = $getProducts->getId();
                    }
                }
                
                if (empty($result['GncProducts'][$k])) {
                    try {
                        $getGncProducts = GncProduct::where('order_id', $result['GncOrder'])->where('product_part_number', $product->getProductPartNumber())->first();
                    } catch(\Exception $e) {
                        throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                    }
                    if (! empty($getGncProducts) && ! empty($getGncProducts->getId())) {
                        $result['GncProducts'][$k] = $getGncProducts->getId();
                    }
                }
            }
            
        }
        
        if ($result['PaymentLink'] === false) {
            try {
                $getPaymentLink = PaymentLink::where('senetic_orders_payments_link_so_id', $gncOrder->getId())->first();
            } catch(\Exception $e) {
                throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
            }
            if (! empty($getPaymentLink) && ! empty($getPaymentLink->getId())) {
                $result['PaymentLink'] = $getPaymentLink->getId();
            }
        }
        
        $saveRequest->setResult($result);
    }

    /**
     * Save data in database SNTC
     *
     * @param SntcOrder $order
     *
     * @return void
     */
    protected function saveOrderInSntc(SaveRequest &$saveRequest)
    {
        $orderNumber = 0;
        $microsoftCspData = '';

        $order = $saveRequest->getOrder();
        $cspAccount = $saveRequest->getCspAccount();

        if ($cspAccount instanceof CspAccount) {
            $hasAccount = $cspAccount->isHasAccount() ? 'Y' : 'N';
            $msDomain = $cspAccount->getMsDomain();
            $partnerAccount = $cspAccount->getPartnerAccount();

            if (!empty($msDomain) && !empty($partnerAccount)) {
                $microsoftCspData = 'has-account: ' . $hasAccount . "\r\n";
                $microsoftCspData .= 'ms-domain: ' . $msDomain . "\r\n";
                $microsoftCspData .= 'Partner Account: ' . $partnerAccount;
            }
        }
        
        $getOrderNumber = SntcOrderNumbers::where('shop_number', 1)->orderBy('order_number', 'desc')->limit(1)->first();
        
        if (empty($getOrderNumber) || empty($getOrderNumber->getOrderNumber())) {
            throw new \Exception(PHP_EOL.'(SaveOrder:#'.$order->getEcommerceOrderId().')  Throw in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error: Fail get next order number.');
        }
        $orderNumber = $getOrderNumber->getOrderNumber()+1;
        $ok = false;
        $max_repeats = 10;
        
        while (! $ok) {
            $sntcOrderNumber = (new SntcOrderNumbers)->setShopNumber(1)
                                                     ->setOrderNumber($orderNumber);
            try {
                $sntcOrderNumber->save();
                $ok = true;
            } catch(\Exception $e) {
                $orderNumber ++;
            }
            
            $max_repeats --;
            if ($max_repeats < 10) {
                break;
            }
        }
        
        if ($ok === false) {
            throw new \Exception(PHP_EOL.'(SaveOrder:#'.$order->getEcommerceOrderId().')  Throw in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error: Fail enumerate next order number');
        }
        
        // NOTE Od kiedy jest inny sposób wybrania numeru zamówienia można by było zapisać model w zasadzie ... tylko jak zrobić NOW() ?
        $query = "
                INSERT INTO senetic_orders
                (
                    `order_category`,
                    `order_categoryType`,
                    `order_clientType`,
                    `order_domain`,
                    `order_subdomain_path`,
                    `order_lang`,
                    `order_number`,
                    `order_added`,
                    `order_added_Ym`,
                    `order_author`,
                    `order_status`,
                    `order_currency`,
                    `order_currency1`,
                    `order_subtotal`,
                    `order_subtotal1`,
                    `order_vat_amount`,
                    `order_vat_amount1`,
                    `order_total`,
                    `order_total1`,
                    `order_coupon_code`,
                    `order_weborder`,
                    `order_authcode`,
                    `order_currency_kurs_do_euro`,
                    `order_currency_kurs_do_pln`,
                    `order_AxPaymTerm`,
                    `order_payment_id`,
                    `order_shipping_id`,
                    `order_payed`,
                    `order_hash`,
                    `order_payment_fee`,
                    `order_payment_fee_gross`,
                    `order_payment_total`,
                    `order_payment_delivery_fee`,
                    `order_shipping`,
                    `order_vat_rate`,
                    `order_invoice_currency`,
                    `order_CustomerType`,
                    `order_AxSalesOrderDataAreaId`,
                    `order_web_company`,
                    `order_payment_gateway_id`,
                    `order_customer_id`,
                    `order_service_delivery_name`,
                    `order_service_delivery_provider`,
                    `order_service_delivery_price`,
                    `order_service_delivery_estimated_date`,
                    `order_service_delivery_access_point`,
                    `order_AxDlvMode`,
                    `order_AxSendInvoiceByEmail`,
                    `order_AxAutosave`,
                    `order_r_date`,
                    `order_AxSalesOrderType`,
                    `order_delivery_warning_message`,
                    `order_microsoft_csp_data`,
                    `order_ecommerce_order_id`
                )
                VALUES
                (
                    '".$order->getCategory()."',
                    '".$order->getCategoryType()."',
                    '".$order->getClientType()."',
                    '".$order->getDomain()."',
                    '".$order->getSubdomainPath()."',
                    '".$order->getLang()."',
                    '".$orderNumber."',
                    NOW(),
                    DATE_FORMAT(NOW(),\"%Y%m\"),
                    '".$order->getAuthor()."',
                    '".$order->getStatus()."',
                    '".$order->getCurrency()."',
                    '".$order->getCurrency1()."',
                    '".$order->getSubtotal()."',
                    '".$order->getSubtotal1()."',
                    '".$order->getVatAmount()."',
                    '".$order->getVatAmount1()."',
                    '".$order->getTotal()."',
                    '".$order->getTotal1()."',
                    '".$order->getCouponCode()."',
                    '".$order->getWeborder()."',
                    '".$order->getAuthcode()."',
                    '".$order->getCurrencyKursDoEuro()."',
                    '".$order->getCurrencyKursDoPln()."',
                    '".$order->getAxPaymTerm()."',
                    '".$order->getPaymentId()."',
                    '".$order->getShippingId()."',
                    '".$order->getPayed()."',
                    '".$order->getHash()."',
                    '".$order->getPaymentFee()."',
                    '".$order->getPaymentFeeGross()."',
                    '".$order->getPaymentTotal()."',
                    '".$order->getPaymentDeliveryFee()."',
                    '".$order->getShipping()."',
                    '".$order->getVatRate()."',
                    '".$order->getInvoiceCurrency()."',
                    '".$order->getCustomerType()."',
                    '".$order->getAxSalesOrderDataAreaId()."',
                    '".$order->getWebCompany()."',
                    '".$order->getPaymentGatewayId()."',
                    '".$order->getCustomerId()."',
                    '".$order->getServiceDeliveryName()."',
                    '".$order->getServiceDeliveryProvider()."',
                    '".$order->getServiceDeliveryPrice()."',
                    '".$order->getServiceDeliveryEstimatedDate()."',
                    '".$order->getServiceDeliveryAccessPoint()."',
                    '".$order->getAxDlvMode()."',
                    '".$order->getAxSendInvoiceByEmail()."',
                    '".$order->getAxAutosave()."',
                    '".$order->getRDate()."',
                    '".$order->getAxSalesOrderType()."',
                    '".$order->getDeliveryWarningMessage()."',
                    '".$microsoftCspData."',
                    '".$order->getEcommerceOrderId()."'
                )
        ";

        // Brzydkie ale tak musi być
        try {
            DB::connection('mysql_ecommerce')->insert(DB::raw($query));
        } catch(\Exception $e) {
            throw new \Exception(PHP_EOL.'(SaveOrder:#'.$order->getEcommerceOrderId().')  Throw in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
        }
    }
    
    /**
     * Save copy order in SNTC (gnc_orders)
     * 
     * @param SaveRequest $saveRequest
     * @param SntcOrder $getOrder
     * 
     * @return void
     */
    protected function saveGncOrderInSntc(SaveRequest &$saveRequest, Order $getOrder)
    {
        $result = $saveRequest->getResult();
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        if ($result['GncOrder'] === false) {
                $order->setId(null);

                $gncOrderModel = null;
                try {
                    $gncOrderModel = (new GncOrder)->makeFromArray($order->toArray());
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                try {
                    $gncOrderModel->save();
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                $result['GncOrder'] = $gncOrderModel->getId();
                $saveRequest->setResult($result);
            }

            // Dodanie brakujacych danych po zapisie kopii
            $order->setId((int)$getOrder->getId());
            $order->setAdded((string)$getOrder->getAdded());
            $order->setAddedYm((int)$getOrder->getAddedYm());
            
            $saveRequest->setOrder($order);
    }
    
    /**
     * Save data in SNTC (orders_cdata)
     * 
     * @param SaveRequest $saveRequest
     * 
     * @return void
     */
    protected function saveDataInSntc(SaveRequest &$saveRequest)
    {
        $result = $saveRequest->getResult();
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        
        if ($result['Data'] === false) {
            $data = $this->prepareOrderData($saveRequest);

            $dataModel = null;
            try {
                $dataModel = (new Data)->makeFromArray($data->toArray());
            } catch(\Exception $e) {
                throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
            }
            try {
                $dataModel->save();
            } catch(\Exception $e) {
                throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
            }

            $result['Data'] = $data->getId();
            $saveRequest->setResult($result);
        }
    }
    
    /**
     * Save products In SNTC
     * 
     * @param SaveRequest $saveRequest
     * 
     * @return void
     */
    protected function saveProductsInSntc(SaveRequest &$saveRequest)
    {
        $result = $saveRequest->getResult();
        $order = $saveRequest->getOrder();
        $products = $saveRequest->getProducts();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        
        if (! empty($products)) {
            $newSeneticPids = [];
            $oldSeneticPids = [];
            
            foreach ($products as $k=>$product) {
                $newSeneticPids[] = $product->getProductId();
            }
            
            $getUnifiedPids = UnifiedPid::whereIn('unified_pid', $newSeneticPids)->get();
            
            foreach ($getUnifiedPids as $unifiedPid) {
                $oldSeneticPids[$unifiedPid->getPid()] = $unifiedPid->getLastSeneticPid();
            }
        }
        
        foreach ($products as $k=>$product) {
            $product->setOrderId($order->getId());
            
            if (! empty($oldSeneticPids[$product->getProductId()])) {
                $product->setProductId($oldSeneticPids[$product->getProductId()]);
            }
            
            $productModel = null;
            $gncProductModel = null;
            if (empty($result['Products'][$k])) {
                $result['Products'][$k] = false;
                try {
                    $productModel = (new Product())
                        ->makeFromArray($product->toArray());
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                try {
                    $productModel->save();
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                $result['Products'][$k] = $productModel->getId();
                $saveRequest->setResult($result);
            }

            $product->setOrderId($result['GncOrder']);
            if (empty($result['GncProducts'][$k])) {
                $result['GncProducts'][$k] = false;
                try {
                    $gncProductModel = (new GncProduct)->makeFromArray($product->toArray());
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                try {
                    $gncProductModel->save();
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                $result['GncProducts'][$k] = $gncProductModel->getId();
                $saveRequest->setResult($result);
            }
        }
    }
    
    /**
     * Save in payment link in SNTC
     * 
     * @param SaveRequest $saveRequest
     * 
     * @return void
     */
    protected function savePaymentLink(SaveRequest &$saveRequest)
    {
        $result = $saveRequest->getResult();
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        $paymentLink = $saveRequest->getPaymentLink();
        
        if (! empty($paymentLink) and ! empty($paymentLink->getHash())) {
            $paymentLink->setSoId($order->getId());
            $paymentLink->setOrderNumber($order->getNumber());
            if (! $paymentLink->getCreated()) {
                $paymentLink->setCreated(date('Y-m-d H:i:s'));
            }

            if ($result['PaymentLink'] === false) {
                $paymentLinkModel = null;
                try {
                    $paymentLinkModel = (new PaymentLink)->makeFromArray($paymentLink->toArray());
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }
                try {
                    $paymentLinkModel->save();
                } catch(\Exception $e) {
                    throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
                }

                $result['PaymentLink'] = $paymentLinkModel->getId();
                $saveRequest->setResult($result);
            }
        }
    }

    /**
     * Prepare data for save SNTC order cdata
     *
     * @param SaveRequest $saveRequest
     *
     * @return void
     */
    protected function prepareOrderData(SaveRequest &$saveRequest)
    {
        $order = $saveRequest->getOrder();
        $additionalData = $saveRequest->getAdditionalData();
        $data = $saveRequest->getData();
        $deliveryAddress = $saveRequest->getDeliveryAddress();
        $endUserAddress = $saveRequest->getEndUserAddress();
        $invoiceAddress = $saveRequest->getInvoiceAddress();
        $productsType = $saveRequest->getProductsType();

        $hasInvoiceAdddress = false;
        $hasDeliveryAddress = false;
        $hasEndUserAddress = false;
        $invoiceEqDelivery = false;
        $invoiceEqEndUser = false;

        if (! empty($invoiceAddress)) {
            $hasInvoiceAdddress = true;
        }

        if (! empty($deliveryAddress)) {
            $hasDeliveryAddress = true;
        }

        if (! empty($endUserAddress)) {
            $hasEndUserAddress = true;
        }

        // Sprawdzenie czy adres z faktury jest taki sam jak dostawy
        if (! empty($invoiceAddress) && ! empty($deliveryAddress)) {
            $invoiceEqDelivery = $this->invoiceIsEqDelivery($invoiceAddress, $deliveryAddress);
        }

        // Sprawdzenie czy adres z faktury jest taki sam jak end usera
        if (! empty($invoiceAddress) && ! empty($endUserAddress)) {
            $invoiceEqEndUser = $this->invoiceIsEqEndUser($invoiceAddress, $endUserAddress);
        }

        // Przygotowanie kilku zmiennych pomocniczych
        $prepare = $this->prepareVars($deliveryAddress, $endUserAddress, $invoiceAddress);

        $deliveryCompanyOrName = $prepare['deliveryCompanyOrName'];
        $deliveryFullName = $prepare['deliveryFullName'];
        $endUserFullName = $prepare['endUserFullName'];
        $invoiceFullName = $prepare['invoiceFullName'];

        $data->setId($order->getId());
        $data->setCategory($order->getCategory());

        $this->setCdataAdditionalData($data, $additionalData);

        if ($productsType != ProductType::SOFTWARE && $productsType != ProductType::MIX) {
            $this->setCdataInvoiceAddress($data, $invoiceAddress, $invoiceFullName, 'i');
            if (! $invoiceEqDelivery) {
                $this->setCdataDeliveryAddress($data, $deliveryAddress, $deliveryFullName, $deliveryAddress->getCompany());
            }
        } elseif ($productsType == ProductType::SOFTWARE || $productsType == ProductType::MIX) {
            if ($invoiceEqEndUser) {
                $this->setCdataInvoiceAddress($data, $invoiceAddress, $invoiceFullName, 'i');
            } else {
                $this->setCdataInvoiceAddress($data, $invoiceAddress, $invoiceFullName, 'ir');
                $this->setCdataEndUserAddress($data, $endUserAddress, $endUserFullName);
            }

            if ($productsType == ProductType::MIX) {
                $this->setCdataDeliveryAddress($data, $deliveryAddress, $deliveryFullName, $deliveryCompanyOrName);
            }
        }
        
        $saveRequest->setOrder($order);

        return $data;
    }
    
    /**
     * Select dlvMode - copy from SNTC Cart.class.php
     * 
     * @param SaveRequest $saveRequest
     * 
     * @return void
     */
    protected function defineDlvMode(SaveRequest &$saveRequest)
    {
        $order = $saveRequest->getOrder();
        $ecommerceOrderId = $order->getEcommerceOrderId();
        $productsType = $saveRequest->getProductsType();
        
        try {
            $axShippingMatrix = AxShippingMatrix::where('ax_company', $order->getAxSalesOrderDataAreaId())
                ->where('web_code', $order->getServiceDeliveryWebCode())
                ->where('web_provider', $order->getServiceDeliveryProvider())
                ->where('status', 'Ok')
                ->first();
        } catch(\Exception $e) {
            throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
        }
        
        $AxDlvMode = '';
        if (! empty($axShippingMatrix) && ! empty($axShippingMatrix->getAxCode())) {
            $AxDlvMode = $axShippingMatrix->getAxCode();
        } else {
            if ($productsType == ProductType::SOFTWARE) {
                $webCode = '1';
                $webProvider = 'senetic';
            } elseif ($productsType == ProductType::HARDWARE) {
                $webCode = '2';
                $webProvider = 'senetic';
            }
            
            try {
                $axShippingMatrix = AxShippingMatrix::where('ax_company', $order->getAxSalesOrderDataAreaId())
                    ->where('web_code', $webCode)
                    ->where('web_provider', $webProvider)
                    ->where('status', 'Ok')
                    ->first();
            } catch(\Exception $e) {
                throw new \Exception(PHP_EOL.'(SaveOrder:#'.$ecommerceOrderId.') Thrown in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.$e->getMessage());
            }
            
            if (! empty($axShippingMatrix)) {
                $AxDlvMode = $axShippingMatrix->getAxCode();
            }
        }
        
        $order->setAxDlvMode($AxDlvMode);
        
        $saveRequest->setOrder($order);
    }

    /**
     * Set Invoice Address
     *
     * @param Cdata $data
     * @param AdditionalData $additionalData
     *
     * @return void
     */
    protected function setCdataAdditionalData(Cdata &$data, AdditionalData $additionalData)
    {
        $data->setIp($additionalData->getIp());
        $data->setClientType($additionalData->getClientType());
        $data->setLocalCompany($additionalData->getLocalCompany());
        $data->setLocalStreet($additionalData->getLocalStreet());
        $data->setLocalCity($additionalData->getLocalCity());
        $data->setLocalPersonName($additionalData->getLocalPersonName());
        $data->setLocalPersonSurname($additionalData->getLocalPersonSurname());
        $data->setInvoiceEmail($additionalData->getInvoiceEmail());
        $data->setEic($additionalData->getEic());
        $data->setICodiceFiscale($additionalData->getICodiceFiscale());
        $data->setIrNumber($additionalData->getOrderNumber());
        $data->setIrOfferNumber($additionalData->getOfferNumber());
        $data->setIDodatkoweInformacje($additionalData->getAdditionalData());
    }

    /**
     * Set Invoice Address
     *
     * @param Cdata $data
     * @param InvoiceAddress $invoiceAddress
     * @param string $target i/ir
     *
     * @return void
     */
    protected function setCdataInvoiceAddress(Cdata &$data, InvoiceAddress $invoiceAddress, $invoiceFullName, $target='i')
    {
        switch ($target) {
            case 'i':
                $data->setINazwaFirmy($invoiceAddress->getCompany());
                $data->setINazwaFirmy1($invoiceAddress->getCompany());
                $data->setINipPre($invoiceAddress->getVatPre());
                $data->setINip($invoiceAddress->getVat());
                $data->setIAddress1($invoiceAddress->getStreet());
                $data->setIAddress2('');
                $data->setIAddress3($invoiceAddress->getCity());
                $data->setIAddress4($invoiceAddress->getPostalCode());
                $data->setIAddress5($invoiceAddress->getCountryCode());
                $data->setIImieINazwisko($invoiceFullName);
                $data->setITelefonPre($invoiceAddress->getPhonePre());
                $data->setITelefon($invoiceAddress->getAreaCode().$invoiceAddress->getPhone());
                $data->setIEmail($invoiceAddress->getEmail());
                $data->setIAreaCode($invoiceAddress->getAreaCode());
                $data->setIAmericanState($invoiceAddress->getState());
            break;
            case 'ir':
                $data->setIrNazwaFirmy($invoiceAddress->getCompany());
                $data->setIrNipPre($invoiceAddress->getVatPre());
                $data->setIrNip($invoiceAddress->getVat());
                $data->setIrAddress1($invoiceAddress->getStreet());
                // $data->setIrAddress2('');
                $data->setIrAddress3($invoiceAddress->getCity());
                $data->setIrAddress4($invoiceAddress->getPostalCode());
                $data->setIrAddress5($invoiceAddress->getCountryCode());
                $data->setIrOsobaKontaktowa($invoiceFullName);
                $data->setIrTelefonPre($invoiceAddress->getPhonePre());
                $data->setIrTelefon($invoiceAddress->getPhone());
                $data->setIrEmail($invoiceAddress->getEmail());
            break;
            default:
                throw new \Exception(PHP_EOL.'Throw in file: '.__FILE__.'#'.__LINE__.', method: '.__METHOD__.', Error:'.PHP_EOL.'$target['.$target.'] is not valid. Values i or ir is correct.');
        }
    }

    /**
     * Set Delivery Address
     *
     * @param Cdata $data
     * @param DeliveryAddress $deliveryAddress
     * @param string $deliveryFullName
     * @param string $deliveryCompanyOrName
     *
     * @return void
     */
    protected function setCdataDeliveryAddress(Cdata &$data, DeliveryAddress $deliveryAddress, $deliveryFullName, $deliveryCompanyOrName)
    {
        $data->setSCompany($deliveryCompanyOrName);
        $data->setSStreet($deliveryAddress->getStreet());
        // $data->setSStreetNumber('');
        $data->setSCity($deliveryAddress->getCity());
        $data->setSZip($deliveryAddress->getPostalCode());
        $data->setSCountry($deliveryAddress->getCountryCode());
        $data->setSPerson($deliveryFullName);
        $data->setSPhonePre($deliveryAddress->getPhonePre());
        $data->setSPhone($deliveryAddress->getPhone());
        $data->setSEmail($deliveryAddress->getEmail());
    }

    /**
     * Set EndUser Address
     *
     * @param Cdata $data
     * @param EndUserAddress $endUserAddress
     * @param string $endUserFullName
     *
     * @return void
     */
    protected function setCdataEndUserAddress(Cdata &$data, EndUserAddress $endUserAddress, $endUserFullName)
    {
        $data->setINazwaFirmy($endUserAddress->getCompany());
        $data->setINazwaFirmy1($endUserAddress->getCompany());
        // $data->setINipPre('');
        // $data->setINip('');
        $data->setIAddress1($endUserAddress->getStreet());
        // $data->setIAddress2('');
        $data->setIAddress3($endUserAddress->getCity());
        $data->setIAddress4($endUserAddress->getPostalCode());
        $data->setIAddress5($endUserAddress->getCountryCode());
        $data->setIImieINazwisko($endUserFullName);
        $data->setITelefonPre($endUserAddress->getPhonePre());
        $data->setITelefon($endUserAddress->getPhone());
        $data->setIEmail($endUserAddress->getEmail());
    }
}
