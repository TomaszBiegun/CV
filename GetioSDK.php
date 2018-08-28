<?php

namespace GetioSDK;

/* SERVICES */
use GetioSDK\CustomerService\Customer\DetailRequest;
use GetioSDK\CustomerService\Customer\Order\UserOrderRequest;
use GetioSDK\CustomerService\SalesOrder\SaveCspOrderRequest;
use GetioSDK\CustomerService\SalesOrder\UpdateOrderCspDomainRequest;
use GetioSDK\EcommerceService\EcommerceService;
use GetioSDK\EcommerceService\SeparatePaymentLinkRequest;
use GetioSDK\ExchangeService\Convert\ConvertRequest;
use GetioSDK\IntegrationService\Bisnode\Request\BisnodeCustomerAccountRequest;
use GetioSDK\IntegrationService\Bisnode\Request\BisnodeCustomerListRequest;
use GetioSDK\IntegrationService\Bisnode\Request\BisnodeCustomerSubscriptionsRequest;
use GetioSDK\IntegrationService\Bisnode\Request\BisnodeBillingRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\ValidateAddressRequest;
use GetioSDK\CustomerService\Employees\GetEmployeesRequest;
use GetioSDK\CustomerService\Nimbus\GetCustomerPayments;
use GetioSDK\CustomerService\SalesOrder\UpdatePaymentLinkRequest;
use GetioSDK\IntegrationService\Microsoft\Office365\Request\UpdateCustomerBillingProfileRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\OfferIdRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\TenantRequest;
use GetioSDK\NotificationService\NotificationService;
use GetioSDK\IntegrationService\IntegrationService;
use GetioSDK\ExchangeService\ExchangeService;
use GetioSDK\LogisticService\LogisticService;
use GetioSDK\CustomerService\CustomerService;
use GetioSDK\PaymentService\PaymentService;
use GetioSDK\PaymentService\Proforma\Request\GenerateProformaRequest;
use GetioSDK\PaymentService\ProformaService;
use GetioSDK\ProductService\ProductService;
use GetioSDK\LoggerService\LoggerService;
use GetioSDK\TestService\TestService;
use GetioSDK\Service\AbstractService;

/* INTEGRATION SERVICE */
use GetioSDK\IntegrationService\Microsoft\Partner\Request\SetCustomerBillingProfileRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\UserAccountsForCustomerRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\OrderAddOnsSubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Response\CustomerSubscriptionsResponse;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\AddressFormattingRulesRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CustomerSubscriptionsRequest;
use GetioSDK\IntegrationService\Microsoft\Office365\Request\Office365PostDomainRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\QuantitySubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\ReactiveSubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\ResellerRelationshipRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\TenantIdFromMetadataRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\SuspendSubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\AddOnsSubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\DomainAvailabilityRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\FederationMetadataRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\ProductsForCountryRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\ResetUserPasswordRequest;
use GetioSDK\IntegrationService\Microsoft\Office365\Request\Office365DomainRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Response\CustomerFilterResponse;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\PartnerOfRecordRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CustomerFilterRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\MxRecordDomainRequest;
use GetioSDK\IntegrationService\Microsoft\Office365\Request\CreateTenantRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\SubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CustomerListRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CustomerByIdRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CreateOrderRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\AddOnsOfferRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\CSPProRateRequest;
use GetioSDK\IntegrationService\Microsoft\Azure\Request\ProductListRequest;
use GetioSDK\IntegrationService\Microsoft\Azure\Request\AzureTenantRequest;
use GetioSDK\IntegrationService\Sntc\ProductMeasures\MeasuresUpdateRequest;
use GetioSDK\IntegrationService\Senetic\Csp\Request\CspSendEmailRequest;
use GetioSDK\IntegrationService\Senetic\Csp\Request\CspCustomerRequest;
use GetioSDK\IntegrationService\Sntc\BankAccount\AccountsUpdateRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\OrderRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\MpnIdRequest;
use GetioSDK\IntegrationService\Microsoft\Azure\CreateCustomer;
use GetioSDK\IntegrationService\Vies\ViesValidityRequest;
use GetioSDK\IntegrationService\Vies\VatFormatRequest;
use GetioSDK\IntegrationService\Senetic\SitesRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\AvailableLicensesForCustomerRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\AssignLicensesToUserRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\UpdateCustomerUserRequest;

/* CUSTOMER SERVICE */
use GetioSDK\CustomerService\Operation\AbstractOperation as CustomerAbstractOperation;
use GetioSDK\CustomerService\Customer\Payments\AxCCPaymentsRequest;
use GetioSDK\CustomerService\Authorize\CustomerAuthorizeRequest;
use GetioSDK\CustomerService\Customer\ResponsiblePersonRequest;
use GetioSDK\CustomerService\UpdatePassword\UpdatePassword;
use GetioSDK\CustomerService\Customer\Order\InvoiceRequest;
use GetioSDK\CustomerService\SalesOrder\SaveRequest;
use GetioSDK\CustomerService\Offer\SaveOfferRequest;
use GetioSDK\CustomerService\Customer\Order\SntcStatus;

/* PRODUCT SERVICE */
use GetioSDK\ProductService\Operation\AbstractOperation as ProductAbstractOperation;
use GetioSDK\ProductService\Signal\SignalElasticReindex;
use GetioSDK\ProductService\Signal\SignalRefresh;

/* LOGISTIC SERVICE */
use GetioSDK\LogisticService\DeliveryMethod\DeliveryMethodRequest;
use GetioSDK\LogisticService\AccessPoint\AccessPointRequest;

/* LOGGER SERVICE */
use GetioSDK\LoggerService\Test\Product\TestProductRequest;
use GetioSDK\Service\Logger\Application\ApplicationRequest;
use GetioSDK\Service\Logger\Hardware\HardwareRequest;
use GetioSDK\Service\Logger\Server\ServerRequest;
use GetioSDK\Service\Logger\Task\TaskRequest;

/* PAYMENT SERVICE */
use GetioSDK\PaymentService\AbstractCheckStatusRequest as AbstractPaymentCheckStatusRequest;
use GetioSDK\PaymentService\AbstractExecutePaymentRequest;
use GetioSDK\PaymentService\AvailableMethodsRequest;
use GetioSDK\PaymentService\AbstractPaymentRequest;

/* EXCHANGE SERVICE */
use GetioSDK\ExchangeService\UpdateRate\UpdateRate;

/* NOTIFICATION SERVICE */
use GetioSDK\NotificationService\Email\Email;
use GetioSDK\NotificationService\Sms\Sms;

/* TEST SERVICE */
use GetioSDK\TestService\TestMessage;

/* COMPONENT */
use GetioSDK\Component\AbstractComponent;
use GetioSDK\Component\Executor;

/**
 * Class GetioSDK
 *
 * @package GetioSDK
 * @author Getio <info@getio.com>
 */
class GetioSDK extends AbstractComponent
{
    /**
     * @var null|GetioSDK
     */
    public static $_instance = null;

    /**
     * GetioSDK constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Initialize service's variables
     */
    public function getServices()
    {

    }

    /**
     * Set object of self class name
     *
     * @return void
     */
    public static function createNewInstance()
    {
        self::$_instance = new self();
    }

    /**
     * Magic PHP method to call static functions
     *
     * @param string
     * @param array
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($method, $args)
    {
        if (self::$_instance == null) {
            self::createNewInstance();
        }

        /*self::$_instance->logManager->setMessage('Called static function.')
            ->addContext('method', $method)
            ->addContext('arguments', $args)
            ->logInfo();*/

        try {
            if (! method_exists(self::$_instance, '_' . $method)) {
                throw new \Exception('Method ' . $method . ' not exists');
            }

            return call_user_func_array([self::$_instance, '_' . $method],  $args);
        } catch (\Exception $exception) {
            self::$_instance->logException($exception);

            throw $exception;
        }
    }

    //-----------------------------------------------------
    //-----------------------------------------------------

    /**
     * Send check Azure tenant validity request via Web Service
     *
     * @param FederationMetadataRequest $data
     * @return IntegrationService
     */
    private function _webIsFederationMetadataAvailable(FederationMetadataRequest $data)
    {
        return (new IntegrationService())
            ->isFederationMetadataAvailable(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check Azure tenant validity request on queue
     *
     * @param FederationMetadataRequest $data
     * @return IntegrationService
     */
    private function _queueIsFederationMetadataAvailable(FederationMetadataRequest $data)
    {
        return (new IntegrationService())
            ->isFederationMetadataAvailable(Executor::QUEUE, $data);
    }

    /**
     * Get tenantId from federation XML via Web Service
     *
     * @param FederationMetadataRequest $data
     * @return IntegrationService
     */
    private function _webGetTenantIdFromFederationMetadata(TenantIdFromMetadataRequest $data)
    {
        return (new IntegrationService())
            ->getTenantIdFromFederationMetadata(Executor::WEB_SERVICE, $data);
    }

    /**
     * Get tenantId from federation XML on queue
     *
     * @param FederationMetadataRequest $data
     * @return IntegrationService
     */
    private function _queueGetTenantIdFromFederationMetadata(TenantIdFromMetadataRequest $data)
    {
        return (new IntegrationService())
            ->getTenantIdFromFederationMetadata(Executor::QUEUE, $data);
    }

    /**
     * Send check Office365 domain availability request via Web Service
     *
     * @param DomainAvailabilityRequest $data
     * @return IntegrationService
     */
    private function _webIsDomainAvailable(DomainAvailabilityRequest $data)
    {
        return (new IntegrationService())
            ->isDomainAvailable(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check Office365 domain availability request on queue
     *
     * @param DomainAvailabilityRequest $data
     * @return IntegrationService
     */
    private function _queueIsDomainAvailable(DomainAvailabilityRequest $data)
    {
        return (new IntegrationService())
            ->isDomainAvailable(Executor::QUEUE, $data);
    }

    /**
     * Send check Azure tenant validity request via Web Service
     *
     * @param AzureTenantRequest $data
     * @return IntegrationService
     */
    private function _webIsTenantValid(AzureTenantRequest $data)
    {
        return (new IntegrationService())
            ->isTenantValid(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check Azure tenant validity request on queue
     *
     * @param AzureTenantRequest $data
     * @return IntegrationService
     */
    private function _queueIsTenantValid(AzureTenantRequest $data)
    {
        return (new IntegrationService())
            ->isTenantValid(Executor::QUEUE, $data);
    }

    /**
     * Send check Microsoft reseller MPN ID validity request via Web Service
     *
     * @param MpnIdRequest $data
     * @return IntegrationService
     */
    private function _webIsMpnIdValid(MpnIdRequest $data)
    {
        return (new IntegrationService())
            ->isMpnIdValid(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check Microsoft reseller MPN ID validity request on queue
     *
     * @param MpnIdRequest $data
     * @return IntegrationService
     */
    private function _queueIsMpnIdValid(MpnIdRequest $data)
    {
        return (new IntegrationService())
            ->isMpnIdValid(Executor::QUEUE, $data);
    }

    /**
     * Send check if given domain has office365 post request via Web Service
     *
     * @param MxRecordDomainRequest $data
     * @return IntegrationService
     */
    private function _webHasDomainOffice365Post(MxRecordDomainRequest $data)
    {
        return (new IntegrationService())
            ->hasDomainOffice365Post(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check if given domain has office365 post request on queue
     *
     * @param MxRecordDomainRequest $data
     * @return IntegrationService
     */
    private function _queueHasDomainOffice365Post(MxRecordDomainRequest $data)
    {
        return (new IntegrationService())
            ->hasDomainOffice365Post(Executor::QUEUE, $data);
    }

    /**
     * Send create customer request via Web Service
     *
     * @param CSPProRateRequest $data
     * @return IntegrationService
     */
    private function _webCSPProRate(CSPProRateRequest $data)
    {
        return (new IntegrationService())
            ->getCSPProRate(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send create customer request on queue
     *
     * @param CSPProRateRequest $data
     * @return IntegrationService
     */
    private function _queueCSPProRate(CSPProRateRequest $data)
    {
        return (new IntegrationService())
            ->getCSPProRate(Executor::QUEUE, $data);
    }

    /**
     * Send create customer request via Web Service
     *
     * @param TenantRequest $data
     * @return IntegrationService
     */
    private function _webCreateTenant(TenantRequest $data)
    {
        return (new IntegrationService())
            ->createTenant(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send create customer request on queue
     *
     * @param TenantRequest $data
     * @return IntegrationService
     */
    private function _queueCreateTenant(TenantRequest $data)
    {
        return (new IntegrationService())
            ->createTenant(Executor::QUEUE, $data);
    }

    /**
     * Send update customer billing profile customer request via Web Service
     *
     * @param UpdateCustomerBillingProfileRequest $data
     * @return IntegrationService
     */
    private function _webUpdateCustomerBillingProfile(UpdateCustomerBillingProfileRequest $data)
    {
        return (new IntegrationService())
            ->updateCustomerBillingProfile(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send update customer billing profile customer request on queue
     *
     * @param UpdateCustomerBillingProfileRequest $data
     * @return IntegrationService
     */
    private function _queueUpdateCustomerBillingProfile(UpdateCustomerBillingProfileRequest $data)
    {
        return (new IntegrationService())
            ->updateCustomerBillingProfile(Executor::QUEUE, $data);
    }

    /**
     * Send set billing profile request via Web Service
     *
     * @param SetCustomerBillingProfileRequest $data
     * @return IntegrationService
     */
    private function _webSetCustomerBillingProfile(SetCustomerBillingProfileRequest $data)
    {
        return (new IntegrationService())
            ->setBillingProfile(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send set billing profile request on queue
     *
     * @param SetCustomerBillingProfileRequest $data
     * @return IntegrationService
     */
    private function _queueSetCustomerBillingProfile(SetCustomerBillingProfileRequest $data)
    {
        return (new IntegrationService())
            ->setBillingProfile(Executor::QUEUE, $data);
    }

    /**
     * @param ProductsForCountryRequest $data
     * @return $this
     */
    private function _webProductsForCountry(ProductsForCountryRequest $data)
    {
        return (new IntegrationService())
            ->getProductsForCountry(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param ProductsForCountryRequest $data
     * @return $this
     */
    private function _queueProductsForCountry(ProductsForCountryRequest $data)
    {
        return (new IntegrationService())
            ->getProductsForCountry(Executor::QUEUE, $data);
    }

    /**
     * @param QuantitySubscriptionRequest $data
     * @return $this
     */
    private function _webQuantitySubscription(QuantitySubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->changeQuantitySubscription(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param QuantitySubscriptionRequest $data
     * @return $this
     */
    private function _queueQuantitySubscrption(QuantitySubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->changeQuantitySubscription(Executor::QUEUE, $data);
    }

    /**
     * @param OrderRequest $data
     * @return $this
     */
    private function _webCreateOrder(OrderRequest $data)
    {
        return (new IntegrationService())
            ->createOrder(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param OrderRequest $data
     * @return $this
     */
    private function _queueCreateOrder(OrderRequest $data)
    {
        return (new IntegrationService())
            ->createOrder(Executor::QUEUE, $data);
    }

    /**
     * @param OrderAddOnsSubscriptionRequest $data
     * @return $this
     */
    private function _webCreateAddOnsOrder(OrderAddOnsSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->createAddOnsOrder(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param OrderAddOnsSubscriptionRequest $data
     * @return $this
     */
    private function _queueCreateAddOnsOrder(OrderAddOnsSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->createAddOnsOrder(Executor::QUEUE, $data);
    }

    /**
     * @param ReactiveSubscriptionRequest $data
     * @return $this
     */
    private function _webReactiveSubscription(ReactiveSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->reactiveSubscription(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param ReactiveSubscriptionRequest $data
     * @return $this
     */
    private function _queueReactiveSubscription(ReactiveSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->reactiveSubscription(Executor::QUEUE, $data);
    }

    /**
     * @param SuspendSubscriptionRequest $data
     * @return $this
     */
    private function _webSuspendSubscription(SuspendSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->suspendSubscription(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param SuspendSubscriptionRequest $data
     * @return $this
     */
    private function _queueSuspendSubscription(SuspendSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->suspendSubscription(Executor::QUEUE, $data);
    }

    /**
     * @param CustomerListRequest $data
     * @return $this
     */
    private function _webListOfCustomers(CustomerListRequest $data)
    {
        return (new IntegrationService())
            ->getListOfCustomers(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param BisnodeCustomerListRequest $data
     * @return $this
     */
    private function _webListOfBisnodeCustomers(BisnodeCustomerListRequest $data)
    {
        return (new IntegrationService())
            ->getListOfBisnodeCustomers(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param BisnodeCustomerListRequest $data
     * @return $this
     */
    private function _queueListOfBisnodeCustomers(BisnodeCustomerListRequest $data)
    {
        return (new IntegrationService())
            ->getListOfBisnodeCustomers(Executor::QUEUE, $data);
    }

    /**
     * @param BisnodeCustomerAccountRequest $data
     * @return $this
     */
    private function _webListOfBisnodeCustomerAccounts(BisnodeCustomerAccountRequest $data)
    {
        return (new IntegrationService())
            ->getListOfBisnodeCustomerAccounts(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param BisnodeCustomerAccountRequest $data
     * @return $this
     */
    private function _queueListOfBisnodeCustomerAccounts(BisnodeCustomerAccountRequest $data)
    {
        return (new IntegrationService())
            ->getListOfBisnodeCustomerAccounts(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerListRequest $data
     * @return $this
     */
    private function _queueListOfCustomers(CustomerListRequest $data)
    {
        return (new IntegrationService())
            ->getListOfCustomers(Executor::QUEUE, $data);
    }

    /**
     * @param CustomerByIdRequest $data
     * @return $this
     */
    private function _webCustomerById(CustomerByIdRequest $data)
    {
        return (new IntegrationService())
            ->getCustomerById(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerListRequest $data
     * @return $this
     */
    private function _queueCustomerById(CustomerListRequest $data)
    {
        return (new IntegrationService())
            ->getCustomerById(Executor::QUEUE, $data);
    }

    /**
     * @param CustomerFilterRequest $data
     * @return $this
     */
    private function _webCustomerByCompanyNameOrDomain(CustomerFilterRequest $data)
    {
        return (new IntegrationService())
            ->getCustomerByCompanyNameOrDomain(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerFilterRequest $data
     * @return $this
     */
    private function _queueCustomerByCompanyNameOrDomain(CustomerFilterRequest $data)
    {
        return (new IntegrationService())
            ->getCustomerByCompanyNameOrDomain(Executor::QUEUE, $data);
    }

    /**
     * @param CustomerSubscriptionsRequest $data
     * @return $this
     */
    private function _webCustomerSubscriptions(CustomerSubscriptionsRequest $data)
    {
        return (new IntegrationService())
            ->getCustomerSubscriptions(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerSubscriptionsResponse $data
     * @return $this
     */
    private function _queueCustomerSubscriptions(CustomerSubscriptionsResponse $data)
    {
        return (new IntegrationService())
            ->getCustomerSubscriptions(Executor::QUEUE, $data);
    }

    /**
     * @param BisnodeCustomerSubscriptionsRequest $data
     * @return $this
     */
    private function _webBisnodeCustomerSubscriptions(BisnodeCustomerSubscriptionsRequest $data)
    {
        return (new IntegrationService())
            ->getBisnodeCustomerSubscriptions(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param BisnodeCustomerSubscriptionsRequest $data
     * @return $this
     */
    private function _queueBisnodeCustomerSubscriptions(BisnodeCustomerSubscriptionsRequest $data)
    {
        return (new IntegrationService())
            ->getBisnodeCustomerSubscriptions(Executor::QUEUE, $data);
    }

    /**
     * @param SubscriptionRequest $data
     * @return $this
     */
    private function _webSubscriptionById(SubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->getSubscriptionById(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param SubscriptionRequest $data
     * @return $this
     */
    private function _queueSubscriptionById(SubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->getSubscriptionById(Executor::QUEUE, $data);
    }

    /**
     * @param AddOnsOfferRequest $data
     * @return $this
     */
    private function _webAddOnsForOffer(AddOnsOfferRequest $data)
    {
        return (new IntegrationService())
            ->getAddOnsForOffer(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AddOnsOfferRequest $data
     * @return $this
     */
    private function _queueAddOnsForOffer(AddOnsOfferRequest $data)
    {
        return (new IntegrationService())
            ->getAddOnsForOffer(Executor::QUEUE, $data);
    }

    /**
     * @param AddOnsSubscriptionRequest $data
     * @return $this
     */
    private function _webAddOnsForSubscription(AddOnsSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->getAddOnsForSubscription(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AddOnsSubscriptionRequest $data
     * @return $this
     */
    private function _queueAddOnsForSubscription(AddOnsSubscriptionRequest $data)
    {
        return (new IntegrationService())
            ->getAddOnsForSubscription(Executor::QUEUE, $data);
    }

    /**
     * @param AddressFormattingRulesRequest $data
     * @return $this
     */
    private function _webAddressFormattingRules(AddressFormattingRulesRequest $data)
    {
        return (new IntegrationService())
            ->getAddressFormattingRules(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AddressFormattingRulesRequest $data
     * @return $this
     */
    private function _queueAddressFormattingRules(AddressFormattingRulesRequest $data)
    {
        return (new IntegrationService())
            ->getUserAccountsForCustomer(Executor::QUEUE, $data);
    }

    /**
     * @param UserAccountsForCustomerRequest $data
     * @return $this
     */
    private function _webUserAccountsForCustomer(UserAccountsForCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getUserAccountsForCustomer(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param UserAccountsForCustomerRequest $data
     * @return $this
     */
    private function _queueUserAccountsForCustomer(UserAccountsForCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getUserAccountsForCustomer(Executor::QUEUE, $data);
    }

    /**
     * @param ResellerRelationshipRequest $data
     * @return $this
     */
    private function _webRequestResellerRelationship(ResellerRelationshipRequest $data)
    {
        return (new IntegrationService())
            ->requestResellerRelationship(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param ResellerRelationshipRequest $data
     * @return $this
     */
    private function _queueRequestResellerRelationship(ResellerRelationshipRequest $data)
    {
        return (new IntegrationService())
            ->requestResellerRelationship(Executor::QUEUE, $data);
    }

    /**
     * @param ResetUserPasswordRequest $data
     * @return $this
     */
    private function _webResetUserPasswordForCustomer(ResetUserPasswordRequest $data)
    {
        return (new IntegrationService())
            ->resetUserPasswordForCustomer(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param ResetUserPasswordRequest $data
     * @return $this
     */
    private function _queueResetUserPasswordForCustomer(ResetUserPasswordRequest $data)
    {
        return (new IntegrationService())
            ->resetUserPasswordForCustomer(Executor::QUEUE, $data);
    }

    /**
     * @param CspCustomerRequest $data
     * @return $this
     */
    private function _webSeneticCspCustomer(CspCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getSeneticCspCustomer(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CspCustomerRequest $data
     * @return $this
     */
    private function _queueSeneticCspCustomer(CspCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getSeneticCspCustomer(Executor::QUEUE, $data);
    }

    /**
     * @param PartnerOfRecordRequest $data
     * @return IntegrationService
     */
    private function _webCreatePartnerOfRecord(PartnerOfRecordRequest $data)
    {
        return (new IntegrationService())
            ->createPartnerOfRecord(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param PartnerOfRecordRequest $data
     * @return IntegrationService
     */
    private function _queueCreatePartnerOfRecord(PartnerOfRecordRequest $data)
    {
        return (new IntegrationService())
            ->createPartnerOfRecord(Executor::QUEUE, $data);
    }

    /**
     * @param CspSendEmailRequest $data
     * @return $this
     */
    private function _webCspSendEmail(CspSendEmailRequest $data)
    {
        return (new IntegrationService())
            ->cspSendEmail(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CspSendEmailRequest $data
     * @return $this
     */
    private function _queueCspSendEmail(CspSendEmailRequest $data)
    {
        return (new IntegrationService())
            ->cspSendEmail(Executor::QUEUE, $data);
    }

    /**
     * @param OfferIdRequest $data
     * @return $this
     */
    private function _webOfferIdFromPartNumber(OfferIdRequest $data)
    {
        return (new IntegrationService())
            ->offerIdFromPartNumber(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param OfferIdRequest $data
     * @return $this
     */
    private function _queueOfferIdFromPartNumber(OfferIdRequest $data)
    {
        return (new IntegrationService())
            ->offerIdFromPartNumber(Executor::QUEUE, $data);
    }

    //-----------------------------------------------------
    //-----------------------------------------------------

    /* ## Test Service ## */

    /**
     * Send test message to queue
     *
     * @param TestMessage $message
     * @return TestService
     */
    private function _queueTestMessage(TestMessage $message)
    {
        return (new TestService())
            ->sendTestMessage(Executor::QUEUE, $message);
    }

    /**
     * Send test message to web service
     *
     * @param TestMessage $message
     * @return TestService
     */
    private function _webTestMessage(TestMessage $message)
    {
        return (new TestService())
            ->sendTestMessage(Executor::WEB_SERVICE, $message);
    }

    /* ## END Test Service ## */
    /* ## Logistic Service ## */

    /**
     * Send get delivery methods request on queue
     *
     * @param DeliveryMethodRequest $data
     * @return LogisticService
     */
    private function _queueGetDeliveryMethod(DeliveryMethodRequest $data)
    {
        return (new LogisticService())
            ->getDeliveryMethod(Executor::QUEUE, $data);
    }

    /**
     * Send get delivery methods request on web service
     *
     * @param DeliveryMethodRequest $data
     * @return LogisticService
     */
    private function _webGetDeliveryMethod(DeliveryMethodRequest $data)
    {
        return (new LogisticService())
            ->getDeliveryMethod(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send get access points request on queue
     *
     * @param AccessPointRequest $data
     * @return LogisticService
     */
    private function _queueGetAccessPoint(AccessPointRequest $data)
    {
        return (new LogisticService())
            ->getAccessPoint(Executor::QUEUE, $data);
    }

    /**
     * Send get access points request on web service
     *
     * @param AccessPointRequest $data
     * @return LogisticService
     */
    private function _webGetAccessPoint(AccessPointRequest $data)
    {
        return (new LogisticService())
            ->getAccessPoint(Executor::WEB_SERVICE, $data);
    }

    /* ## END Logistic Service ## */
    /* ## Notification Service ## */

    /**
     * Send mail request on queue
     *
     * @param Email $data
     * @return $this
     */
    private function _queueSendMail(Email $data)
    {
        return (new NotificationService())
            ->sendMail(Executor::QUEUE, $data);
    }

    /**
     * Send mail request on web service
     *
     * @param Email $data
     * @return $this
     */
    private function _webSendMail(Email $data)
    {
        return (new NotificationService())
            ->sendMail(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send sms request on queue
     *
     * @param Sms $data
     * @return NotificationService
     */
    private function _queueSendSms(Sms $data)
    {
        return (new NotificationService())
            ->sendSms(Executor::QUEUE, $data);
    }

    /**
     * Send sms request on web service
     *
     * @param Sms $data
     * @return NotificationService
     */
    private function _webSendSms(Sms $data)
    {
        return (new NotificationService())
            ->sendSms(Executor::WEB_SERVICE, $data);
    }

    /* ## END Notification Service ## */
    /* ## Integration Service ## */

    /**
     * Send check vies validity request via Web Service
     *
     * @param ViesValidityRequest $data
     * @return IntegrationService
     */
    private function _webIsViesValid(ViesValidityRequest $data)
    {
        return (new IntegrationService())
            ->isViesValid(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check vies validity request on queue
     *
     * @param ViesValidityRequest $data
     * @return IntegrationService
     */
    private function _queueIsViesValid(ViesValidityRequest $data)
    {
        return (new IntegrationService())
            ->isViesValid(Executor::QUEUE, $data);
    }

    /**
     * Send check vat number format validity request via Web Service
     *
     * @param VatFormatRequest $data
     * @return IntegrationService
     */
    private function _webIsVatFormatValid(VatFormatRequest $data)
    {
        return (new IntegrationService())
            ->isVatFormatValid(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send check vat number format validity request on queue
     *
     * @param VatFormatRequest $data
     * @return IntegrationService
     */
    private function _queueIsVatFormatValid(VatFormatRequest $data)
    {
        return (new IntegrationService())
            ->isVatFormatValid(Executor::QUEUE, $data);
    }

    /**
     * Send update e-commerce bank accounts request via Web Service
     *
     * @param AccountsUpdateRequest $data
     * @return IntegrationService
     */
    private function _webUpdateEcommerceBankAccounts(AccountsUpdateRequest $data)
    {
        return (new IntegrationService())
            ->updateBankAccounts(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send update e-commerce bank accounts request on queue
     *
     * @param AccountsUpdateRequest $data
     * @return IntegrationService
     */
    private function _queueUpdateEcommerceBankAccounts(AccountsUpdateRequest $data)
    {
        return (new IntegrationService())
            ->updateBankAccounts(Executor::QUEUE, $data);
    }

    /**
     * Send update e-commerce products measures request via Web Service
     *
     * @param MeasuresUpdateRequest $data
     * @return IntegrationService
     */
    private function _webUpdateEcommerceProductsMeasures(MeasuresUpdateRequest $data)
    {
        return (new IntegrationService())
            ->updateProductsMeasures(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send update e-commerce products measures request on queue
     *
     * @param MeasuresUpdateRequest $data
     * @return IntegrationService
     */
    private function _queueUpdateEcommerceProductsMeasures(MeasuresUpdateRequest $data)
    {
        return (new IntegrationService())
            ->updateProductsMeasures(Executor::QUEUE, $data);
    }

    /* ## END Integration Service ## */
    /* ## Exchange Service ## */

    /**
     * Send update rate request on queue
     *
     * @param UpdateRate $data
     * @return ExchangeService
     */
    private function _queueSendExchangeUpdate(UpdateRate $data)
    {
        return (new ExchangeService())
            ->sendRateUpdate(Executor::QUEUE, $data);
    }

    /**
     * Send update rate request on web service
     *
     * @param UpdateRate $data
     * @return ExchangeService
     */
    private function _webSendExchangeUpdate(UpdateRate $data)
    {
        return (new ExchangeService())
            ->sendRateUpdate(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send price conversion request on queue
     *
     * @param ConvertRequest $data
     * @return ExchangeService
     */
    private function _queuePriceConversion(ConvertRequest $data)
    {
        return (new ExchangeService())
            ->priceConvert(Executor::QUEUE, $data);
    }

    /**
     * Send price conversion request on web service
     *
     * @param ConvertRequest $data
     * @return ExchangeService
     */
    private function _webPriceConversion(ConvertRequest $data)
    {
        return (new ExchangeService())
            ->priceConvert(Executor::WEB_SERVICE, $data);
    }

    /* ## END Exchange Service ## */
    /* ## Customer Service ## */

    /**
     * Send operations on queue
     *
     * @param CustomerAbstractOperation $data
     * @return CustomerService
     */
    private function _queueExecuteCustomerOperations(CustomerAbstractOperation $data)
    {
        return (new CustomerService())
            ->addOperation(Executor::QUEUE, $data);
    }

    /**
     * Send operations on web service
     *
     * @param CustomerAbstractOperation $data
     * @return CustomerService
     */
    private function _webExecuteCustomerOperations(CustomerAbstractOperation $data)
    {
        return (new CustomerService())
            ->addOperation(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send update of customer password on queue
     *
     * @param UpdatePassword $data
     * @return CustomerService
     */
    private function _queueUpdateCustomerPassword(UpdatePassword $data)
    {
        return (new CustomerService())
            ->updateUserPassword(Executor::QUEUE, $data);
    }

    /**
     * Send update of customer password on web service
     *
     * @param UpdatePassword $data
     * @return CustomerService
     */
    private function _webUpdateCustomerPassword(UpdatePassword $data)
    {
        return (new CustomerService())
            ->updateUserPassword(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerService\Customer\Enquery\ListRequest $data
     * @return CustomerService
     */
    private function _webGetListEnqueries(\GetioSDK\CustomerService\Customer\Enquery\ListRequest $data)
    {
        return (new CustomerService())
            ->getListEnqueries(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerService\Customer\Enquery\DetailRequest $data
     * @return CustomerService
     */
    private function _webGetDetailsEnquery(\GetioSDK\CustomerService\Customer\Enquery\DetailRequest $data)
    {
        return (new CustomerService())
            ->getDetailsEnquery(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerService\Customer\Order\ListRequest $data
     * @return CustomerService
     */
    private function _webGetListOrders(\GetioSDK\CustomerService\Customer\Order\ListRequest $data)
    {
        return (new CustomerService())
            ->getListOrders(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param CustomerService\Customer\Order\DetailRequest $data
     * @return CustomerService
     */
    private function _webGetDetailsOrder(\GetioSDK\CustomerService\Customer\Order\DetailRequest $data)
    {
        return (new CustomerService())
            ->getDetailsOrder(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param InvoiceRequest $data
     * @return CustomerService
     */
    private function _webGetInvoice(InvoiceRequest $data)
    {
        return (new CustomerService())
            ->getInvoice(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param InvoiceRequest $data
     * @return CustomerService
     */
    private function _queueGetInvoice(InvoiceRequest $data)
    {
        return (new CustomerService())
            ->getInvoice(Executor::QUEUE, $data);
    }

    /**
     * @param DetailRequest $data
     * @return CustomerService
     */
    private function _webGetCustomerDetails(\GetioSDK\CustomerService\Customer\DetailRequest $data)
    {
        return (new CustomerService())
            ->getCustomerDetails(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param DetailRequest $data
     * @return CustomerService
     */
    private function _queueGetCustomerDetails(\GetioSDK\CustomerService\Customer\DetailRequest $data)
    {
        return (new CustomerService())
            ->getCustomerDetails(Executor::QUEUE, $data);
    }

    /**
     * @param ResponsiblePersonRequest $data
     * @return CustomerService
     */
    private function _queueGetResponsiblePerson(ResponsiblePersonRequest $data)
    {
        return (new CustomerService())
            ->getResponsiblePerson(Executor::QUEUE, $data);
    }

    /**
     * @param ResponsiblePersonRequest $data
     * @return CustomerService
     */
    private function _webGetResponsiblePerson(ResponsiblePersonRequest $data)
    {
        return (new CustomerService())
            ->getResponsiblePerson(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send save sales order reqest on queue
     *
     * @param SaveRequest $data
     * @return CustomerService
     */
    private function _queueSaveOrder(SaveRequest $data)
    {
        return (new CustomerService())
            ->saveOrder(Executor::QUEUE, $data);
    }

    /**
     * Send save sales order reqest on web service
     *
     * @param SaveRequest $data
     * @return CustomerService
     */
    private function _webSaveOrder(SaveRequest $data)
    {
        return (new CustomerService())
            ->saveOrder(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send save CSP sales order request on queue
     *
     * @param SaveCspOrderRequest $data
     * @return CustomerService
     */
    private function _queueSaveCspOrder(SaveCspOrderRequest $data)
    {
        return (new CustomerService())
            ->saveCspOrder(Executor::QUEUE, $data);
    }

    /**
     * Send save CSP sales order request on web service
     *
     * @param SaveCspOrderRequest $data
     * @return CustomerService
     */
    private function _webSaveCspOrder(SaveCspOrderRequest $data)
    {
        return (new CustomerService())
            ->saveCspOrder(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send update Microsoft csp domain data on SNTC order request on queue
     *
     * @param UpdateOrderCspDomainRequest $data
     * @return CustomerService
     */
    private function _queueUpdateOrderCspDomain(UpdateOrderCspDomainRequest $data)
    {
        return (new CustomerService())
            ->updateOrderCspDomain(Executor::QUEUE, $data);
    }

    /**
     * Send update Microsoft csp domain data on SNTC order request on web service
     *
     * @param UpdateOrderCspDomainRequest $data
     * @return CustomerService
     */
    private function _webUpdateOrderCspDomain(UpdateOrderCspDomainRequest $data)
    {
        return (new CustomerService())
            ->updateOrderCspDomain(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send save offer reqest on queue
     *
     * @param SaveOfferRequest $data
     * @return CustomerService
     */
    private function _queueSaveOffer(SaveOfferRequest $data)
    {
        return (new CustomerService())
            ->saveOffer(Executor::QUEUE, $data);
    }

    /**
     * Send save offer reqest on web service
     *
     * @param SaveOfferRequest $data
     * @return CustomerService
     */
    private function _webSaveOffer(SaveOfferRequest $data)
    {
        return (new CustomerService())
            ->saveOffer(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send payment link update reqest on queue
     *
     * @param UpdatePaymentLinkRequest $data
     * @return CustomerService
     */
    private function _queueUpdatePaymentLink(UpdatePaymentLinkRequest $data)
    {
        return (new CustomerService())
            ->updatePaymentLink(Executor::QUEUE, $data);
    }

    /**
     * Send payment link update reqest on web service
     *
     * @param UpdatePaymentLinkRequest $data
     * @return CustomerService
     */
    private function _webUpdatePaymentLink(UpdatePaymentLinkRequest $data)
    {
        return (new CustomerService())
            ->updatePaymentLink(Executor::WEB_SERVICE, $data);
    }

    /**
     * Authorize customer by email and password
     *
     * @param  CustomerAuthorizeRequest $data
     * @return CustomerService
     */
    private function _queueAuthorizeCustomer(CustomerAuthorizeRequest $data)
    {
        return (new CustomerService())
            ->authorize(Executor::QUEUE, $data);
    }

    /**
     * Authorize customer by email and password
     *
     * @param  CustomerAuthorizeRequest $data
     * @return CustomerService
     */
    private function _webAuthorizeCustomer(CustomerAuthorizeRequest $data)
    {
        return (new CustomerService())
            ->authorize(Executor::WEB_SERVICE, $data);
    }

    /**
     * Update employees data from old Senetic_1 DB
     *
     * @param  GetEmployeesRequest $data
     * @return CustomerService
     */
    private function _webGetEmpolyees(GetEmployeesRequest $data)
    {
        return (new CustomerService())
            ->getEmployeesData(Executor::WEB_SERVICE, $data);
    }

    /**
     * Get all customer payments from AX
     *
     * @param GetCustomerPayments $data
     * @return CustomerService
     */
    private function _webGetCustomerPayments(GetCustomerPayments $data)
    {
        return (new CustomerService())
            ->getCustomerPayments(Executor::WEB_SERVICE, $data);
    }

    /**
     * Get all customer CC payments from AX
     *
     * @param AxCCPaymentsRequest $data
     * @return CustomerService
     */
    private function _queueGetCustomerAXCCPayments(AxCCPaymentsRequest $data)
    {
        return (new CustomerService())
            ->getAxCCPaymets(Executor::QUEUE, $data);
    }

    /**
     * Get all customer CC payments from AX
     *
     * @param AxCCPaymentsRequest $data
     * @return CustomerService
     */
    private function _webGetCustomerAXCCPayments(AxCCPaymentsRequest $data)
    {
        return (new CustomerService())
            ->getAxCCPaymets(Executor::WEB_SERVICE, $data);
    }

    /**
     * Change order status in SNTC
     *
     * @param SntcStatus $data
     * @return CustomerService
     */
    private function _webChangeSntcOrderStatus(SntcStatus $data)
    {
        return (new CustomerService())
            ->changeSntcOrderStatus(Executor::WEB_SERVICE, $data);
    }

    /**
     * Get Order by OrderNumber and CustomerId
     *
     * @param UserOrderRequest $data
     * @return CustomerService
     */
    private function _webGetUserOrderByNumber(UserOrderRequest $data)
    {
        return (new CustomerService())
            ->getUserOrderByNumber(Executor::WEB_SERVICE, $data);
    }

    /**
     * Get Order by OrderNumber and CustomerId
     *
     * @param UserOrderRequest $data
     * @return CustomerService
     */
    private function _queueGetUserOrderByNumber(UserOrderRequest $data)
    {
        return (new CustomerService())
            ->getUserOrderByNumber(Executor::QUEUE, $data);
    }

    /* ## END Customer Service ## */
    /* ## Product Service ## */

    /**
     * Send signal elastic reindex on queue
     *
     * @param SignalElasticReindex $data
     * @return ProductService
     */
    private function _queueSignalElasticReindex(SignalElasticReindex $data)
    {
        return (new ProductService())
            ->sendSignalElasticReindex(Executor::QUEUE, $data);
    }

    /**
     * Send signal elastic reindex on web service
     *
     * @param SignalElasticReindex $data
     * @return ProductService
     */
    private function _webSignalElasticReindex(SignalElasticReindex $data)
    {
        return (new ProductService())
            ->sendSignalElasticReindex(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send signal refresh on queue
     *
     * @param SignalRefresh $data
     * @return ProductService
     */
    private function _queueSignalRefresh(SignalRefresh $data)
    {
        return (new ProductService())
            ->sendSignalRefresh(Executor::QUEUE, $data);
    }

    /**
     * Send signal refresh on web service
     *
     * @param SignalRefresh $data
     * @return ProductService
     */
    private function _webSignalRefresh(SignalRefresh $data)
    {
        return (new ProductService())
            ->sendSignalRefresh(Executor::WEB_SERVICE, $data);
    }

    /**
     * Send operations on queue
     *
     * @param ProductAbstractOperation $data
     * @return ProductService
     */
    private function _queueExecuteProductOperations(ProductAbstractOperation $data)
    {
        return (new ProductService())
            ->addOperation(Executor::QUEUE, $data);
    }

    /**
     * Send operations on web service
     *
     * @param ProductAbstractOperation $data
     * @return ProductService
     */
    private function _webExecuteProductOperations(ProductAbstractOperation $data)
    {
        return (new ProductService())
            ->addOperation(Executor::WEB_SERVICE, $data);
    }

    /* ## END Product Service ## */
    /* ## Payment Service ## */

    /**
     * Payment request on queue
     *
     * @param ProductAbstractOperation $data
     * @return PaymentService
     */
    private function _queuePaymentRequest(AbstractPaymentRequest $data)
    {
        return (new PaymentService())
            ->paymentRequest(Executor::QUEUE, $data);
    }

    /**
     * Payment request on web service
     *
     * @param ProductAbstractOperation $data
     * @return PaymentService
     */
    private function _webPaymentRequest(AbstractPaymentRequest $data)
    {
        return (new PaymentService())
            ->paymentRequest(Executor::WEB_SERVICE, $data);
    }

    /**
     * Check Payment request on queue
     *
     * @param  AbstractPaymentCheckStatusRequest $data
     * @return PaymentService
     */
    private function _queueCheckPaymentStatus(AbstractPaymentCheckStatusRequest $data)
    {
        return (new PaymentService())
            ->checkStatus(Executor::QUEUE, $data);
    }

    /**
     * Check Payment request on web service
     *
     * @param  AbstractPaymentCheckStatusRequest $data
     * @return PaymentService
     */
    private function _webCheckPaymentStatus(AbstractPaymentCheckStatusRequest $data)
    {
        return (new PaymentService())
            ->checkStatus(Executor::WEB_SERVICE, $data);
    }

    /**
     * Execute payment request on queue
     *
     * @param  AbstractExecutePaymentRequest $data
     * @return PaymentService
     */
    private function _queueExecutePayment(AbstractExecutePaymentRequest $data)
    {
        return (new PaymentService())
            ->executePaymentRequest(Executor::QUEUE, $data);
    }

    /**
     * Execute payment request on web service
     *
     * @param  AbstractExecutePaymentRequest $data
     * @return PaymentService
     */
    private function _webExecutePayment(AbstractExecutePaymentRequest $data)
    {
        return (new PaymentService())
            ->executePaymentRequest(Executor::WEB_SERVICE, $data);
    }

    /**
     * Execute get available payment methods request on queue
     *
     * @param  AvailableMethodsRequest $data
     * @return PaymentService
     */
    private function _queueGetAvailableMethods(AvailableMethodsRequest $data)
    {
        return (new PaymentService())
            ->availableMethods(Executor::QUEUE, $data);
    }

    /**
     * Execute get available payment methods request on web service
     *
     * @param  AvailableMethodsRequest $data
     * @return PaymentService
     */
    private function _webGetAvailableMethods(AvailableMethodsRequest $data)
    {
        return (new PaymentService())
            ->availableMethods(Executor::WEB_SERVICE, $data);
    }

    /**
     * Execute generate proforma request on queue
     *
     * @param  GenerateProformaRequest $data
     * @return PaymentService
     */
    private function _queueGenerateProforma(GenerateProformaRequest $data)
    {
        return (new ProformaService())
            ->generateProforma(Executor::QUEUE, $data);
    }

    /**
     * Execute generate proforma request on web service
     *
     * @param  GenerateProformaRequest $data
     * @return PaymentService
     */
    private function _webGenerateProforma(GenerateProformaRequest $data)
    {
        return (new ProformaService())
            ->generateProforma(Executor::WEB_SERVICE, $data);
    }
    
    /* ## END Payment Service ## */

    /* ## Logger Service ## */

    /**
     * @param TestProductRequest $data
     * @return LoggerService
     */
    private function _queueTestProduct(TestProductRequest $data)
    {
        return (new LoggerService())
            ->testProduct(Executor::QUEUE, $data);
    }

    /**
     * @param TestProductRequest $data
     * @return LoggerService
     */
    private function _webTestProduct(TestProductRequest $data)
    {
        return (new LoggerService())
            ->testProduct(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AbstractService $service
     * @return AbstractService
     */
    private function _webHardwareStatus(AbstractService $service, HardwareRequest $data)
    {
        return $service->getHardwareStatus(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AbstractService $service
     * @param ServerRequest $data
     * @return AbstractService
     */
    private function _webServerLogs(AbstractService $service, ServerRequest $data)
    {
        return $service->getServerLogs(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AbstractService $service
     * @param TaskRequest $data
     * @return AbstractService
     */
    private function _webTaskLogs(AbstractService $service, TaskRequest $data)
    {
        return $service->getTaskLogs(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AbstractService $service
     * @param ApplicationRequest $data
     * @return AbstractService
     */
    private function _webApplicationLogs(AbstractService $service, ApplicationRequest $data)
    {
        return $service->getApplicationLogs(Executor::WEB_SERVICE, $data);
    }

    /* ## END Logger Service ## */

    /**
     * @param ValidateAddressRequest $data
     * @return AbstractService
     */
    private function _webValidateAddress(ValidateAddressRequest $data)
    {
        return (new IntegrationService())
            ->validateAddress(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param ValidateAddressRequest $data
     * @return AbstractService
     */
    private function _queueValidateAddress(ValidateAddressRequest $data)
    {
        return (new IntegrationService())
            ->validateAddress(Executor::QUEUE, $data);
    }

    /**
     * @param SitesRequest $data
     * @return $this
     */
    private function _queueSitesRequest(SitesRequest $data)
    {
        return (new IntegrationService())
            ->getSites(Executor::QUEUE, $data);
    }

    /**
     * @param BisnodeBillingRequest $data
     * @return $this
     */
    private function _webBisnodeBillingRequest(BisnodeBillingRequest $data)
    {
        return (new IntegrationService())
            ->getBisnodeBillingRequest(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param BisnodeBillingRequest $data
     * @return $this
     */
    private function _queueBisnodeBillingRequest(BisnodeBillingRequest $data)
    {
        return (new IntegrationService())
            ->getBisnodeBillingRequest(Executor::QUEUE, $data);
    }

    /**
     * @param SitesRequest $data
     * @return $this
     */
    private function _webSitesRequest(SitesRequest $data)
    {
        return (new IntegrationService())
            ->getSites(Executor::WEB_SERVICE, $data);
    }

    /* ## Ecommerce Service ## */

    /**
     * @param SeparatePaymentLinkRequest $data
     * @return $this
     */
    private function _queueSeparatePaymentLink(SeparatePaymentLinkRequest $data)
    {
        return (new EcommerceService())
            ->getSeparatePaymentLink(Executor::QUEUE, $data);
    }

    /**
     * @param SeparatePaymentLinkRequest $data
     * @return $this
     */
    private function _webSeparatePaymentLink(SeparatePaymentLinkRequest $data)
    {
        return (new EcommerceService())
            ->getSeparatePaymentLink(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AvailableLicensesForCustomerRequest $data
     * @return $this
     */
    private function _webAvailableLicensesForCustomer(AvailableLicensesForCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getAvailableLicensesForCustomer(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AvailableLicensesForCustomerRequest $data
     * @return $this
     */
    private function _queueAvailableLicensesForCustomer(AvailableLicensesForCustomerRequest $data)
    {
        return (new IntegrationService())
            ->getAvailableLicensesForCustomer(Executor::QUEUE, $data);
    }

    /**
     * @param AssignLicensesToUserRequest $data
     * @return $this
     */
    private function _webAssignLicensesToUser(AssignLicensesToUserRequest $data)
    {
        return (new IntegrationService())
            ->assignLicensesToUser(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param AssignLicensesToUserRequest $data
     * @return $this
     */
    private function _queueAssignLicensesToUser(AssignLicensesToUserRequest $data)
    {
        return (new IntegrationService())
            ->assignLicensesToUser(Executor::QUEUE, $data);
    }

    /**
     * @param UpdateCustomerUserRequest $data
     * @return $this
     */
    private function _webUpdateCustomerUser(UpdateCustomerUserRequest $data)
    {
        return (new IntegrationService())
            ->updateCustomerUser(Executor::WEB_SERVICE, $data);
    }

    /**
     * @param UpdateCustomerUserRequest $data
     * @return $this
     */
    private function _queueUpdateCustomerUser(UpdateCustomerUserRequest $data)
    {
        return (new IntegrationService())
            ->updateCustomerUser(Executor::QUEUE, $data);
    }

    /* ## END Ecommerce Service ## */
}
