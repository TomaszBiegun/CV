<?php

namespace App\Module\Basket\Service;

use App\Module\Account\Model\AxCustomer;
use App\Module\Core\Directives\PriceFormatDirective;
use App\Module\Basket\Model\Item\Software\SoftwareItem;
use App\Module\Basket\Exception\BadItemTypeException;
use App\Module\Contact\Service\CompanyService;
use App\Module\Core\Model\Currency;
use App\Module\Core\Service\CountryService;
use App\Module\Core\Service\CurrencyService;
use App\Module\PriceList\Model\Price\PriceList;
use App\Module\PriceList\Model\Price\PriceRuleMarkup;
use App\Module\PriceList\Model\Statics\StaticPriceList;
use App\Module\PriceList\Model\Statics\StaticPriceListLine;
use App\Module\PriceList\Service\PriceListService;
use App\Module\PriceList\Service\StaticPriceListService;
use GetioCSP\Model\AxSubscription;
use GetioCSP\Model\Customer;
use GetioCSP\Model\LockedPrice;
use GetioCSP\Model\ParentSubscription;
use GetioCSP\Model\Subscription;
use GetioCSP\Service\AccountService;
use GetioCSP\Service\SubscriptionService;
use GetioCSP\Model\Account;
use GetioCSP\Model\CountryAccount;
use App\Module\Employee\Service\EmployeeService;
use App\Module\Product\Service\Item\ItemService;
use App\Module\Account\Service\UserService;
use App\Module\Product\Model\Item\Item;


use App\Module\Basket\Model\Item\AbstractItem;
use App\Module\Basket\Model\Order\Address\AccessPointSourceAddress;
use App\Module\Basket\Model\Order\Address\OrderInvoiceAddress;
use App\Module\Basket\Exception\InvalidItemQuantityException;
use App\Module\Basket\Model\VatConfig;
use App\Module\Basket\Service\Address\InvoiceAddressService;
use App\Module\Basket\Service\Item\Software\SoftwareService;
use App\Module\Basket\Service\Item\Software\CspItemService;
use App\Module\Basket\Model\Order\Address\UpsAccessPoint;
use App\Module\Core\Model\Country;
use App\Module\Core\Model\Site;
use GetioSDK\CustomerService\Customer\DetailRequest;
use GetioSDK\CustomerService\Customer\Order\OrderPaymentsRequest;
use GetioSDK\ExchangeService\Convert\Conversion;
use GetioSDK\ExchangeService\Convert\ConvertRequest;
use GetioSDK\ExchangeService\UpdateRate\UpdateRate;
use GetioSDK\IntegrationService\Microsoft\Partner\Request\QuantitySubscriptionRequest;
use GetioSDK\IntegrationService\Microsoft\Partner\Response\SubscriptionResponse;
use GetioSDK\TaskManager\AbstractTask;
use GetioSDK\CustomerService\SalesOrder\DeliveryAddress;
use GetioSDK\CustomerService\SalesOrder\AdditionalData;
use GetioSDK\CustomerService\SalesOrder\EndUserAddress;
use GetioSDK\CustomerService\SalesOrder\InvoiceAddress;
use GetioSDK\CustomerService\SalesOrder\PaymentLink;
use GetioSDK\CustomerService\SalesOrder\SaveRequest;
use App\Module\Basket\Model\Order\OrderAdditional;
use GetioSDK\CustomerService\SalesOrder\Products;
use App\Module\Core\Application\AbstractService;
use GetioSDK\CustomerService\SalesOrder\Product;
use App\Module\Basket\Exception\OrderException;
use GetioSDK\IntegrationService\Microsoft\Partner\SDK\Models\Subscriptions\SubscriptionStatus;
use GetioSDK\NotificationService\Email\Email;
use App\Module\Coupon\Service\CouponService;
use App\Module\Core\Service\ExchangeService;
use Illuminate\Database\Eloquent\Collection;
use GetioSDK\CustomerService\ProductType;
use App\Module\Core\Service\RoundService;
use App\Module\Basket\Model\Order\Order;
use App\Module\Core\Service\SiteService;
use GetioSDK\TaskManager\WebServiceTask;
use App\Module\Employee\Model\Employee;
use GetioCSP\Service\CspService;
use App\Module\Contact\Model\Contact;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use App\Module\Auth\Model\User;
use Illuminate\Http\Request;
use GetioSDK\GetioSDK;
use Carbon\Carbon;
use App\Module\Customer\Service\CustomerOrderService;
use GetioCSP\Service\MicrosoftPartnerCenter\MicrosoftSubscriptionService;
use App\Module\Core\Application\Application;
use GetioSDK\IntegrationService\Microsoft\Partner\SDK\Models\Orders\OrderLineItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Class OrderService
 * @package App\Module\Basket\Service
 */
class OrderService extends AbstractService
{
    public static $ITEMS_TYPE_HARDWARE = 'hardware';
    public static $ITEMS_TYPE_SOFTWARE = 'software';
    public static $ITEMS_TYPE_MIX = 'mix';
    public static $ITEMS_TYPE_EMPTY = 'empty';

    public static $BLOCKED_NONE = 'None';
    public static $BLOCKED_COUNTRIES = 'Countries';
    public static $BLOCKED_LICENSE = 'License';
    public static $BLOCKED_VIES = 'Vies';

    /**
     * @var string
     */
    protected static $_ORDER_TYPE_LICENCES = 'Licenses';

    /**
     * @var string
     */
    protected static $_ORDER_TYPE_STANDARD = 'Standard';

    /**
     * @var SiteService|null
     */
    protected $siteService = null;

    /**
     * @var ItemService|null
     */
    protected $itemService = null;

    /**
     * @var UserService|null
     */
    protected $userService = null;

    /**
     * @var SoftwareService|null
     */
    protected $softwareService = null;

    /**
     * @var CspItemService|null
     */
    protected $cspItemService = null;

    /**
     * @var CouponService|null
     */
    protected $couponService = null;

    /**
     * @var RoundService|null
     */
    protected $roundService = null;

    /**
     * @var InvoiceAddressService|null
     */
    protected $invoiceAddressService = null;

    /**
     * @var null|CspService
     */
    protected $cspService = null;

    /**
     * @var ExchangeService|null
     */
    protected $exchangeService = null;

    /**
     * @var PaymentService|null
     */
    protected $paymentService = null;

    /**
     * @var DeliveryService|null
     */
    protected $deliveryService = null;

    /**
     * @var VatService|null
     */
    protected $vatService = null;

    /**
     * @var CustomerOrderService|null
     */
    protected $customerOrderService = null;

    /**
     * @var SubscriptionService|null
     */
    protected $subscriptionService = null;

    /**
     * @var MicrosoftSubscriptionService|null
     */
    protected $microsoftSubscriptionService = null;

    /**
     * @var AccountService|null
     */
    protected $accountService = null;

    /**
     * @var CompanyService|null
     */
    protected $companyService = null;

    /**
     * @var EmployeeService|null
     */
    protected $employeeService = null;

    /**
     * @var CountryService|null
     */
    protected $countryService = null;

    /**
     * @var PriceListService|null
     */
    protected $priceListService = null;

    /**
     * @var StaticPriceListService|null
     */
    protected $staticPriceListService = null;

    /**
     * Initialize service's variables
     */
    public function getServices()
    {
        $this->siteService = $this->serviceManager->get(SiteService::class);
        $this->itemService = $this->serviceManager->get(ItemService::class);
        $this->userService = $this->serviceManager->get(UserService::class);

        $this->softwareService = $this->serviceManager->get(SoftwareService::class);
        $this->roundService = $this->serviceManager->get(RoundService::class);
        $this->cspItemService = $this->serviceManager->get(CspItemService::class);
        $this->invoiceAddressService = $this->serviceManager->get(InvoiceAddressService::class);
        $this->couponService = $this->serviceManager->get(CouponService::class);
        $this->cspService = $this->serviceManager->get(CspService::class);
        $this->exchangeService = $this->serviceManager->get(ExchangeService::class);
        $this->paymentService = $this->serviceManager->get(PaymentService::class);
        $this->deliveryService = $this->serviceManager->get(DeliveryService::class);
        $this->vatService = $this->serviceManager->get(VatService::class);
        $this->customerOrderService = $this->serviceManager->get(CustomerOrderService::class);
        $this->subscriptionService = $this->serviceManager->get(SubscriptionService::class);
        $this->microsoftSubscriptionService = $this->serviceManager->get(MicrosoftSubscriptionService::class);
        $this->accountService = $this->serviceManager->get(AccountService::class);
        $this->companyService = $this->serviceManager->get(CompanyService::class);
        $this->employeeService = $this->serviceManager->get(EmployeeService::class);
        $this->countryService = $this->serviceManager->get(CountryService::class);
        $this->priceListService = $this->serviceManager->get(PriceListService::class);
        $this->staticPriceListService = $this->serviceManager->get(StaticPriceListService::class);
    }

    /*
       __________  ____  ______
      / ____/ __ \/ __ \/ ____/
     / /   / / / / /_/ / __/   
    / /___/ /_/ / _, _/ /___   
    \____/\____/_/ |_/_____/   
    */

    /**
     * Checks if application has set order
     *
     * @return bool
     */
    public function exists()
    {
        $order = $this->get();

        return ($order !== null);
    }

    /**
     * Return order object by id
     * @param  integer $id
     * @return Order|null
     */
    public function getById($id)
    {
        return Order::find((int)$id);
    }

    /**
     * Returns active Order instance from Application
     *
     * @return Order
     */
    public function get()
    {
        $order = $this->application->getOrder();

        if (!$this->application->getCspCustomer()) {
            return null;
        }

        if ($order == null) {
            $this->reload();
        }

        if (!$order instanceof Order || $order->getClosed()) {
            $order = $this->create();
        }

        // Check if logged user is same as saved in order
        $this->checkOrderUser($order);

        return $order;
    }

    /**
     * Delete current order
     *
     * @return bool
     */
    public function deleteCurrentOrder()
    {

        if (!$this->exists()) {
            return true;
        }

        $order = $this->get();
        $orderId = $order->getId();

        // wszystkie elementy po order id

        $order->softwareItems()->get()->each(function ($item, $key) {
            $item->delete();
        });

        $order->invoiceAddress()->delete();

        if ($order->delete()) {
            $newOrder = $this->create();

            return true;
        }

        return false;
    }

    /**
     * Reload order from session Id
     */
    public function reload()
    {
        $sessionId = $this->application->getSessionId();

        // Set order to application
        $order = Order::where([
            'session' => $sessionId,
            'closed' => false
        ])->first();

        if ($order instanceof Order) {
            $this->application->setOrder($order);
        }
    }

    /**
     * Creates new Order instance for Application
     */
    public function create()
    {
        // Get customer's axCompany
        $customer = $this->application->getCspCustomer();
        $axCompany = $customer instanceof AxCustomer ? strtoupper($customer->getDataAreaId()) : '';

        // Get cusetomer's currency
        $country = $this->countryService->getByIso($customer->getCountry());
        $currency = $country instanceof Country ? $country->getCurrency() : new Currency();
        $vatRate = $country instanceof Country ? $country->getVatRate() : (float)1.00;

        $order = (new Order())
            ->setSession($this->application->getSessionId())
            ->setCurrency($currency)
            ->setVatRate($vatRate)
            ->setAxCompany($axCompany);

        $user = $this->userService->getLogged();
        $order->setUser($user);
        $order = $this->setDefaultInvoiceAddress($order);

        $order->save();
        $this->application->setOrder($order);

        return $order;
    }

    /**
     * Updates Application's Order instance to given one
     *
     * @param Order $order
     */
    public function update(Order $order)
    {
        $order->save();

        $this->application->setOrder($order);
    }

    /**
     * Returns collection of all orders products
     *
     * @return Collection
     */
    public function reloadItems()
    {
        $orderItems = $this->getSoftwareItems()
            ->keyBy('id');

        $ids = $orderItems->keys()->toArray();
        $items = $this->itemService->getByIds($ids);
        $items = $this->toOrderItems($orderItems, $items);

        $softwareItem = new Collection();

        foreach ($items as $item) {
            if ($item instanceof SoftwareItem) {
                $softwareItem->push($item);

                continue;
            }
        }

        return (new Collection())
            ->put(Order::$_SOFTWARE, $softwareItem);
    }

    /**
     * Closes current order
     *
     * @param Order $order
     */
    public function close(Order $order)
    {
        // Change order close status
        $order->setClosed(true);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Refresh all order data
     */
    public function refresh()
    {
        // Get Order item from Database
        $this->reload();

        // Get Order Instance
        $order = $this->get();

        // Refresh total amount
        $order->setAmount($this->countNetProRate());
        $order->setGrossAmount($this->countGrossProRate());

        // Update Order instance
        $this->update($order);
    }

    /**
     * @return int
     */
    public function getNetProRate()
    {
        $order = $this->get();

        return $order->getAmount();
    }

    /**
     * @return int
     */
    public function countNetProRate()
    {
        $orderItems = $this->getAddedItems();
        $netProRate = 0;

        foreach ($orderItems as $orderItem) {
            $netProRate += $orderItem->getAmount();
        }

        return $netProRate;
    }

    /**
     * @return int
     */
    public function countGrossProRate()
    {
        $orderItems = $this->getAddedItems();
        $grossProRate = 0;

        foreach ($orderItems as $orderItem) {
            $grossProRate += $orderItem->getGrossAmount();
        }

        return $grossProRate;
    }

    /**
     * Checks if proceed to next order steep has been blocked
     *
     * @return bool
     */
    public function isBlocked()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getBlocked() != self::$BLOCKED_NONE;
    }

    /**
     * Returns message text corresponding with orders blockade state
     *
     * @return string
     */
    public function getBlockadeMessage()
    {
        // Get Order Instance
        $order = $this->get();

        return trans('basket.partials.blockade-popup.message.' . $order->getBlocked());
    }

    /**
     * Checks if logged user is same as saved in order
     *
     * @param Order $order
     */
    protected function checkOrderUser(Order &$order)
    {
        // Get logged user
        $user = $this->userService->getLogged();

        if ($user->getId() != $order->getUserId()) {
            // Update orders user data
            $order->setUser($user);

            $this->setDefaultInvoiceAddress($order);
            $this->update($order);
        }
    }

    /**
     * @return int
     */
    protected function getLoggedCustomerId()
    {
        // Get Order Instance
        $order = $this->get();

        $loggedUser = $order->getUser();

        if (!$loggedUser instanceof User) {
            return 0;
        }

        return $loggedUser->getSourceCustomerId();
    }

    /**
     * @throws OrderException
     * @return void
     */
    public function setLoggedUserData()
    {
        $order = $this->get();

        if ($order == null) {
            throw new OrderException('Order doesn\'t exist.');
        }

        $this->setDefaultInvoiceAddress($order);
        $this->update($order);
    }

    /**
     * @return boolean
     */
    public function isHardware()
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function isSoftware()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function isMix()
    {
        return false;
    }


    /*
        ____  ___   _____ __ __ ____________   __________________  ________    ____  ____  __________  ___  ______________  _   _______
       / __ )/   | / ___// //_// ____/_  __/  /  _/_  __/ ____/  |/  / ___/   / __ \/ __ \/ ____/ __ \/   |/_  __/  _/ __ \/ | / / ___/
      / __  / /| | \__ \/ ,<  / __/   / /     / /  / / / __/ / /|_/ /\__ \   / / / / /_/ / __/ / /_/ / /| | / /  / // / / /  |/ /\__ \ 
     / /_/ / ___ |___/ / /| |/ /___  / /    _/ /  / / / /___/ /  / /___/ /  / /_/ / ____/ /___/ _, _/ ___ |/ / _/ // /_/ / /|  /___/ / 
    /_____/_/  |_/____/_/ |_/_____/ /_/    /___/ /_/ /_____/_/  /_//____/   \____/_/   /_____/_/ |_/_/  |_/_/ /___/\____/_/ |_//____/  
    */

    /**
     * @param Item $item
     * @param $quantity
     * @param null $parentSubscriptionId
     * @param null $offerId
     * @param null $type
     * @return bool
     * @throws InvalidItemQuantityException
     * @throws OrderException
     */
    public function addItem(Item $item, $quantity, $parentSubscriptionId = null, $offerId = null, $type = null)
    {
        $quantity = (int)$quantity; // Convert to integer
        $vatRate = $this->getOrderVatRate();

        if ($parentSubscriptionId) {
            $parentSubscription = $this->subscriptionService->getParentById($parentSubscriptionId);
            $priceNet = $this->getSubscriptionPrice($parentSubscription);
            $proRate = $this->calcProRate($priceNet, $quantity, $parentSubscription->getSubscriptionBillingCycle(), $parentSubscription->getEndDate());

            if (!$parentSubscription instanceof ParentSubscription) {
                throw new OrderException('Parent Subscription ID must be set');
            }
        } else if ($offerId) {
            /** @var AxCustomer $customer */
            $customer = $this->application->getCspCustomer();

            $country                = $this->countryService->getByIso($customer->getCountry());
            $priceList              = $this->priceListService->get($country->getPriceListId());
            $monthlyPriceRuleMarkup = $this->priceListService->getAccurateMarkup($priceList->getPriceRuleId(), PriceRuleMarkup::$_BILLING_CYCLE_MONTHLY, PriceRuleMarkup::$_LICENSE_TYPE_ALL);
            $annualPriceRuleMarkup  = $this->priceListService->getAccurateMarkup($priceList->getPriceRuleId(), PriceRuleMarkup::$_BILLING_CYCLE_ANNUAL, PriceRuleMarkup::$_LICENSE_TYPE_ALL);
            $this->currencyService  = $this->serviceManager->get(CurrencyService::class);
            $msCurrencies           = $this->currencyService->MsCurrenciesSelectOptions();

            $currencyCodeFrom   = $priceList->getCurrencyCode();
            $currencyCodeTo     = $country->getCurrencyCode();

            $currencyCode = $currencyCodeFrom;
            if (in_array($currencyCodeTo, $msCurrencies)) {
                $currencyCode = $currencyCodeTo;
            }

            $rate = 1.00;
            $item = $this->itemService->getAccurateItem($offerId, $currencyCode);

            if (strtolower($currencyCode) != strtolower($currencyCodeTo)) {
                $conversion = (new Conversion())
                    ->setFrom($currencyCode)
                    ->setTo($currencyCodeTo)
                    ->setValue(10000);

                $request = (new ConvertRequest())
                    ->setConversion($conversion);

                $response = \GetioSDK::webPriceConversion($request)->send();
                $rate = $response->getTask()->getData()->getConvertedValue();

                $rate = $rate / 10000;
                if (($response->getStatus() != AbstractTask::STATUS_DONE) || (is_null($rate))) {
                    throw new \Exception('No currency rate.');
                }

                $currencyCode = $currencyCodeTo;
            }

            $partNumber = "'" . substr($offerId, 0, 13) . "'";
            $accurateItem = $this->itemService->getItems([$partNumber], $currencyCode, $priceList->getCurrencyCode())[0];

            $priceBase = (int) str_replace([',', '.', ' '], '', trim((string) $accurateItem->ListPrice));
            $priceMonthly = bcmul($priceBase * $rate * $monthlyPriceRuleMarkup->getMarkup(), 1);
            $priceAnnually = bcmul($priceBase * $rate * $annualPriceRuleMarkup->getMarkup() * 12, 1);

            if ($type == ParentSubscription::$_BILLING_CYCLE_ANNUALY) {
                $priceNet = $priceAnnually;
                $proRate = $priceNet;
            } else {
                $priceNet = $priceMonthly;
                $proRate = $this->calcProRate($priceNet, $quantity, $type, Carbon::now()->endOfMonth());
            }
        }

        if ($quantity == 0) {
            throw new InvalidItemQuantityException('Quantity must be less or greater than 0');
        }

        $customer = $this->application->getCspCustomer();
        $country = $this->countryService->getByIso($customer->getCountry());

        $priceNetRounded = $this->roundService->round($priceNet, true, RoundService::SEPARATE_NET);
        $priceGrossRounded = $this->roundService->round(round($priceNet * $vatRate, 0), false, RoundService::SEPARATE_GROSS);

        $proRateNetRounded = $this->roundService->round($proRate, true, RoundService::SEPARATE_NET);
        $proRateGrossRounded = $this->roundService->round(($proRate * (float)$vatRate), false, RoundService::SEPARATE_GROSS);

        $ammountNetRounded = $this->roundService->round((int)$quantity * $proRateNetRounded, true, RoundService::CART_LINE_NET);
        $ammountGrossRounded = $this->roundService->round((int)$quantity * $proRateGrossRounded, false, RoundService::CART_LINE_GROSS);

        // create new order item
        $orderItem = $this->softwareService
            ->createOrderItem($item, $quantity, $vatRate)
            ->setSubscriptionId('')
            ->setParentId($parentSubscriptionId ? $parentSubscription->getId() : null)
            ->setPrice($priceNetRounded)
            ->setGrossPrice($priceGrossRounded)
            ->setAmount($ammountNetRounded)
            ->setGrossAmount($ammountGrossRounded)
            ->setProRate($proRateNetRounded)
            ->setGrossProRate($proRateGrossRounded)
            ->setOfferId($offerId)
            ->setSubscriptionData(!$parentSubscriptionId ? json_encode([
                'name'          => $accurateItem->OfferDisplayName,
                'billingCycle'  => $type,
                'partNumber'    => $accurateItem->ShortOfferId,
                'License'       => $accurateItem->LicenseAgreementType,
            ]) : '')
            ->save();

        // Refresh order data
        $this->refresh();

        return $orderItem;
    }

    /**
     * Sets quantity of Item to given quantity
     *
     * @param Item $item
     * @param $quantity
     * @return SoftwareItem
     * @throws InvalidItemQuantityException
     */
    public function setItemQuantity(Item $item, $quantity, $subscriptionId = null)
    {
        if (!$item->isSoftware() || !$item->isCsp()) {
            throw new BadItemTypeException('Bad item type');
        }

        $quantity = (int)$quantity; // Convert to integer

        if ($quantity == 0) {
            return $this->removeItem($item, $subscriptionId);
        }

        $orderItem = $this->getItem($item, $subscriptionId);

        $vatRate = $this->getOrderVatRate();
        $amount = $this->softwareService->calculateAmount($item, $quantity);
        $grossAmount = $this->softwareService->calculateGrossAmount($item, $quantity, $vatRate);

        // Calculate CSP Item Pro Rate
        $this->cspService->calculateItemProRate($orderItem, true);


        $orderItem->setQuantity($quantity);
        $orderItem->setAmount($amount);
        $orderItem->setGrossAmount($grossAmount);

        $orderItem->save();

        if (!$orderItem->isReturn()) {
            $coupon = $this->getCoupon();

            if ($coupon != null) {
                $this->couponService->apply($coupon);
            }
        }

        // Refresh order data
        $this->refresh();

        return $orderItem;
    }

    /**
     * @param Item $item
     * @param $quantity
     * @param null $parentSubscriptionId
     * @param bool $add
     * @param bool $newParent
     * @param null $offerId
     * @param string $type annualy|monthly
     * @return SoftwareItem|null
     * @throws InvalidItemQuantityException
     * @throws OrderException
     * @throws \Exception
     */
    public function subscriptionSetItemQuantity(Item $item, $quantity, $parentSubscriptionId = null, $add = false, $newParent = false, $offerId = null, $type = null)
    {
        $quantity = (int)$quantity; // Convert to integer
        $isReturn = $quantity < 0;

        if (!$parentSubscriptionId && $isReturn && !$newParent) {
            throw new OrderException('Subscription ID must be set if return');
        }

        if ($quantity == 0) {
            throw new InvalidItemQuantityException('Quantity must be less or greater than 0');
        }

        $orderItem = $this->getItem($item, $parentSubscriptionId);

        if (!$orderItem instanceof SoftwareItem) {
            return $this->addItem($item, $quantity, $parentSubscriptionId, $offerId, $type);
        }

        $quantity = abs($quantity); // Convert to positive value

        $amount = $orderItem->getProRate() * $quantity;
        $grossAmount = $orderItem->getProRate() * $quantity * $this->getOrderVatRate();

        if ($isReturn) {
            $quantity = $quantity * (-1);
        }

        $orderItem->setQuantity($quantity);
        $orderItem->setAmount($amount);
        $orderItem->setGrossAmount($grossAmount);
        $orderItem->setReturn($isReturn);

        $orderItem->save();

        // Refresh order data
        $this->refresh();

        return $orderItem;
    }

    /**
     * Removes given item from the order entirely
     *
     * @param Item $item
     */
    public function removeItem(Item $item, $subscriptionId = null)
    {
        $this->softwareService->removeOrderItem($item, $subscriptionId);

        $this->refresh();
    }



    /*
        __________________  ________
       /  _/_  __/ ____/  |/  / ___/
       / /  / / / __/ / /|_/ /\__ \ 
     _/ /  / / / /___/ /  / /___/ / 
    /___/ /_/ /_____/_/  /_//____/  
                                    
    */

    /**
     * Returns type of items in order
     *
     * @return string
     */
    public function getItemsType()
    {
        return self::$ITEMS_TYPE_SOFTWARE;
    }

    /**
     * Returns set for order Item or null
     *
     * @param int $itemId
     * @return SoftwareItem|null
     */
    public function getItemById($itemId)
    {

        // Search in software items
        return $this->softwareService
            ->getOrderItemById((int)$itemId);
    }

    /**
     * @param string $subscriptionId
     * @return SoftwareItem|null
     */
    public function getOrderItemBySubscriptionId($subscriptionId)
    {
        if ($this->countSoftwareItems() == 0) {
            return null;
        }

        $order = $this->get();
        return SoftwareItem::where('order_id', $order->getId())
            ->where('subscription_id', $subscriptionId)
            ->first();
    }

    /**
     * Returns quantity of item with given Item Id
     *
     * @param int $itemId
     * @return int
     */
    public function getItemQuantityById($itemId)
    {
        $item = $this->getItemById((int)$itemId);

        // Return item quantity in order
        if ($item == null) {
            return 0;
        }

        return $item->getQuantity();
    }

    /**
     * Returns collection of all standard (not configurable) orders products
     *
     * @return Collection
     */
    public function getStandardItems()
    {
        $allItems = $this->getItems();

        return $allItems->filter(function (AbstractItem $orderItem) {
            if ($orderItem->isCspItem()) {
                return false;
            }

            return true;
        });
    }

    /**
     * Returns collection of all not return items
     *
     * @return SoftwareItem[]|Collection
     */
    public function getAddItems()
    {
        $allItems = $this->getItems();

        return $allItems->filter(function (AbstractItem $orderItem) {
            if ($orderItem->isReturn()) {
                return false;
            }

            return true;
        });
    }

    /**
     * Returns collection of all return items
     *
     * @return Collection
     */
    public function getRemoveItems()
    {
        $allItems = $this->getItems();

        return $allItems->filter(function (AbstractItem $orderItem) {
            if (!$orderItem->isReturn()) {
                return false;
            }

            return true;
        });
    }

    /**
     * Returns set for order Item or null
     *
     * @param Item $item
     * @return SoftwareItem|null
     */
    public function getItem(Item $item, $parentSubscriptionId = null)
    {
        return $this->getSoftwareItem($item, $parentSubscriptionId);
    }

    /**
     * Returns set for order given Item if its Sodtware one
     *
     * @param Item $item
     * @return SoftwareItem|null
     */
    public function getSoftwareItem(Item $item, $parentSubscriptionId = null)
    {
        if ($parentSubscriptionId != null) {
            return $this->softwareService
                ->getOrderItemWithSubscriptionId($item, $parentSubscriptionId);
        }

        return $this->softwareService
            ->getOrderItem($item);
    }

    /**
     * @return Collection
     */
    public function getItems()
    {
        $order = $this->get();

        if ($order == null) {
            return new Collection();
        }

        $softwareItems = $order->getSoftwareItems();

        return (new Collection())
            ->merge($softwareItems);
    }

    /**
     * @return Collection
     */
    public function getAddedItems()
    {
        $order = $this->get();

        if ($order == null) {
            return new Collection();
        }

        $softwareItems = SoftwareItem::where('order_id', $order->getId())
            ->where('return', 0)
            ->get();

        return (new Collection())
            ->merge($softwareItems);
    }

    /**
     * @return Collection
     */
    public function getSoftwareItems()
    {
        return $this->softwareService->getOrderItems();
    }

    /**
     * @param Collection $orderItems
     * @param Collection $items
     * @return Collection
     */
    public function toOrderItems(Collection $orderItems, Collection $items)
    {
        foreach ($orderItems as $orderItem) {
            $id = $orderItem->getItemId();

            if ($items->has($id)) {
                $orderItem->setRelation('item', $items->get($id));
            }
        }

        return $orderItems;
    }

    /**
     * Returns collection of all orders CSP products
     *
     * @return Collection
     */
    public function getCspItems()
    {
        return $this->cspItemService->getOrderItems();
    }

    /**
     * @return Collection
     */
    public function getSoftwareStandardItems()
    {
        return $this->softwareService->getOrderStandardItems();
    }

    /**
     * Returns collection of all products from given Order
     *
     * @param Order $order
     * @return Collection
     */
    public function getItemsFromOrder(Order $order)
    {
        return $this->getSoftwareItemsFromOrder($order);
    }

    /**
     * @param Order $order
     * @return Collection
     */
    public function getSoftwareItemsFromOrder(Order $order)
    {
        return $this->softwareService->getItemsFromOrder($order);
    }


    /**
     * Returns number of all the items in the order
     *
     * @return int
     */
    public function count()
    {
        $order = $this->get();

        if ($order == null) {
            return 0;
        }

        return $order->count();
    }

    /**
     * Returns number of add the items in the order
     *
     * @return int
     */
    public function countAdd()
    {
        $order = $this->get();

        if ($order == null) {
            return 0;
        }

        return $order->countAdd();
    }

    /**
     * Returns number of Software items in the order
     *
     * @return int
     */
    public function countSoftwareItems()
    {
        $order = $this->get();

        if ($order == null) {
            return 0;
        }

        return $order->countSoftwareItems();
    }

    /**
     * Returns number of remove the items in the order
     *
     * @return int
     */
    public function countRemove()
    {
        $order = $this->get();

        if ($order == null) {
            return 0;
        }

        return $order->countRemove();
    }

    /**
     * Returns number of software items in the order
     *
     * @return int
     */
    public function countSoftware()
    {
        $order = $this->get();

        if ($order == null) {
            return 0;
        }

        return $order->countSoftware();
    }

    /**
     * Returns number of CSP configurator items in the Order
     *
     * @return int
     */
    public function countCsp()
    {
        return (int)$this->cspItemService
            ->countOrderItems();
    }

    /**
     * Returns original gross value of order's items
     *
     * @return int
     */
    public function getGrossItemsAmount()
    {
        $items = $this->getAddedItems();

        $price = 0;
        foreach ($items as $item) {
            if ($item->isCspMonthItem() || (!$item->isCspMonthItem() && $item->isCspItem())) {
                $price += $item->getSoftwareItemCsp()->getGrossAmountProRate() * $item->getQuantity();
                continue;
            }

            $price += $item->getGrossAmount();
        }

        return $price;
    }

    /**
     * Returns original net value of added order's items
     *
     * @return int
     */
    public function getNetItemsAmount()
    {
        $items = $this->getAddedItems();
        $price = 0;
        foreach ($items as $item) {
            if ($item->isCspMonthItem() || (!$item->isCspMonthItem() && $item->isCspItem())) {
                $price += $item->getSoftwareItemCsp()->getAmountProRate() * $item->getQuantity();
                continue;
            }

            $price += $item->getAmount();
        }

        return $price;
    }

    /**
     * Returns original tax value of order's items
     *
     * @return int
     */
    public function getTaxItemsAmount()
    {
        return $this->getGrossItemsAmount() - $this->getNetItemsAmount();
    }

    /**
     * Returns discounted net value of order's items
     *
     * @return int
     */
    public function getNetItemsAmountDiscounted()
    {
        return $this->getNetItemsAmount() - $this->getDiscount();
    }

    /**
     * Returns discounted gross value of order's items
     *
     * @return int
     */
    public function getGrossItemsAmountDiscounted()
    {
        return $this->getGrossItemsAmount() - $this->getGrossDiscount();
    }

    /**
     * @return int
     */
    public function getVatItemsAmount()
    {
        return $this->getGrossItemsAmountDiscounted() - $this->getNetItemsAmountDiscounted();
    }

    /**
     * Returns original gross value of add order's items
     *
     * @return int
     */
    public function getGrossAddItemsAmount()
    {
        $items = $this->getAddedItems();
        $price = 0;

        foreach ($items as $item) {
            if ($item->isReturn()) {
                continue;
            }

            if ($item->isCspMonthItem()) {
                $price += $item->getSoftwareItemCsp()->getGrossAmountProRate();
            } else {
                $price += $item->getGrossAmount();
            }
        }

        return $price;
    }

    /**
     * Returns original net value of add order's items
     *
     * @return int
     */
    public function getNetAddItemsAmount()
    {
        $items = $this->getAddedItems();
        $price = 0;

        foreach ($items as $item) {
            if ($item->isReturn()) {
                continue;
            }

            if ($item->isCspMonthItem()) {
                $price += $item->getSoftwareItemCsp()->getAmountProRate();
            } else {
                $price += $item->getAmount();
            }
        }

        return $price;
    }

    /**
     * Returns original tax value of add order's items
     *
     * @return int
     */
    public function getTaxAddItemsAmount()
    {
        return $this->getGrossAddItemsAmount() - $this->getNetAddItemsAmount();
    }

    /**
     * Returns original gross value of add order's items
     *
     * @return int
     */
    public function getGrossRemoveItemsAmount()
    {
        $items = $this->getItems();
        $price = 0;

        foreach ($items as $item) {
            if (!$item->isReturn()) {
                continue;
            }

            if ($item->isCspMonthItem()) {
                $price += $item->getSoftwareItemCsp()->getGrossAmountProRate();
            } else {
                $price += $item->getGrossAmount();
            }
        }

        return $price;
    }

    /**
     * Returns original net value of add order's items
     *
     * @return int
     */
    public function getNetRemoveItemsAmount()
    {
        $items = $this->getItems();
        $price = 0;

        foreach ($items as $item) {
            if (!$item->isReturn()) {
                continue;
            }

            if ($item->isCspMonthItem()) {
                $price += $item->getSoftwareItemCsp()->getAmountProRate();
            } else {
                $price += $item->getAmount();
            }
        }

        return $price;
    }

    /**
     * Returns original tax value of add order's items
     *
     * @return int
     */
    public function getTaxRemoveItemsAmount()
    {
        return $this->getGrossRemoveItemsAmount() - $this->getNetRemoveItemsAmount();
    }

    /**
     * Returns original net value of order
     *
     * @return int
     */
    public function getNetAmount()
    {
        $order = $this->get();

        return $order->getAmount();
    }

    /**
     * Returns original gross value of order
     *
     * @return int
     */
    public function getGrossAmount()
    {
        $price = 0;

        $price += $this->getGrossItemsAmount();

        return round($price, 0);
    }

    /**
     * Returns net value of order decreased by discount
     *
     * @return int
     */
    public function getNetAmountDiscounted()
    {
        $price = 0;

        $price += $this->getNetItemsAmountDiscounted();
        $price += $this->getNetDeliveryAmountDiscounted();

        return round($price, 0);
    }

    /**
     * Returns gross value of order decreased by discount
     *
     * @return int
     */
    public function getGrossAmountDiscounted()
    {
        $price = 0;

        $price += $this->getGrossItemsAmountDiscounted();

        return round($price, 0);
    }

    /**
     * Returns original net price of selected delivery method
     *
     * @return int
     */
    public function getNetDeliveryAmount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getDeliveryCost();
    }

    /**
     * Returns discounted net price of selected delivery method
     *
     * @return int
     */
    public function getNetDeliveryAmountDiscounted()
    {
        return $this->getNetDeliveryAmount();
    }

    /**
     * Returns original gross price of selected delivery method
     *
     * @return int
     */
    public function getGrossDeliveryAmount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getGrossDeliveryCost();
    }

    /**
     * Returns discounted gross price of selected delivery method
     *
     * @return int
     */
    public function getGrossDeliveryAmountDiscounted()
    {
        return $this->getGrossDeliveryAmount();
    }

    /*
        ___    ____  ____  ____  ________________
       /   |  / __ \/ __ \/ __ \/ ____/ ___/ ___/
      / /| | / / / / / / / /_/ / __/  \__ \\__ \ 
     / ___ |/ /_/ / /_/ / _, _/ /___ ___/ /__/ / 
    /_/  |_/_____/_____/_/ |_/_____//____/____/  
                                                 
    */

    /**
     * @param Order $order
     * @return Order
     */
    public function setDefaultInvoiceAddress(Order $order)
    {
        $invoiceAddress = $this->invoiceAddressService
            ->create($this->userService->getInvoiceAddress());

        $customer = Application::getInstance()->getCspCustomer();

        if ($customer) {
            $invoiceAddress = $this->invoiceAddressService
                ->fillAddress($customer);

            if ($invoiceAddress && $invoiceAddress->getCountry()) {
                $invoiceAddress->save();
                $order->setInvoiceAddress($invoiceAddress);
            }
        }

        return $order;
    }

    /**
     * Sets orders invoice address
     *
     * @param OrderInvoiceAddress $invoiceAddress
     */
    public function setInvoiceAddress(OrderInvoiceAddress $invoiceAddress)
    {
        // Get Order Instance
        $order = $this->get();

        // If invoice address has not been set already save obtained address and set its ID to the order
        $oldInvoiceAddress = $this->getInvoiceAddress();

        if (empty($oldInvoiceAddress->getId())) {
            $invoiceAddress->save();
            $order->setInvoiceAddressId($invoiceAddress->getId());
        } else {
            // There has been set invoice address already - overwrite its data
            $newInvoiceAddressData = $invoiceAddress->toArray();

            $oldInvoiceAddress->updateFromArray($newInvoiceAddressData);
            $oldInvoiceAddress->save();
        }

        // Update Orders AxCompany
        $this->setOrderAxCompany($invoiceAddress->getCountry()->getAxCompany());

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns invoice address set for Order
     *
     * @return OrderInvoiceAddress
     */
    public function getInvoiceAddress()
    {
        // Get Order Instance
        $order = $this->get();

        // Get invoice address data by invoice address ID hold in Order
        $invoiceAddressId = (int)$order->getInvoiceAddressId();
        $invoiceAddress = OrderInvoiceAddress::where('id', $invoiceAddressId)->first();

        if (!empty($invoiceAddress)) {
            return $invoiceAddress;
        }

        // If invoice address is empty, get logged user invoice address
        $user = $this->userService->getLogged();
        $order->setUser($user);

        $this->setDefaultInvoiceAddress($order);

        // And update Order instance
        $this->update($order);

        // Return empty InvoiceAddress by default
        return (new OrderInvoiceAddress());
    }

    /*
       ____  ____  ____  __________     _____ ___ _    ________
      / __ \/ __ \/ __ \/ ____/ __ \   / ___//   | |  / / ____/
     / / / / /_/ / / / / __/ / /_/ /   \__ \/ /| | | / / __/   
    / /_/ / _, _/ /_/ / /___/ _, _/   ___/ / ___ | |/ / /___   
    \____/_/ |_/_____/_____/_/ |_|   /____/_/  |_|___/_____/   
    */

    /**
     * Finnish order process
     */
    public function save()
    {
        // Get Order Instance
        $order = $this->get();

        // Prepare objects to save
        $sntcOrder = $this->prepareOrderToSave($order);
        $paymentLink = $this->preparePaymentLinkToSave($order);
        $invoiceAddress = $this->prepareInvoiceAddressToSave($order);
        $products = $this->prepareProductsToSave($order);
        $additionalData = $this->prepareAdditionalDataToSave($order);

        // Get products type
        $productsType = ProductType::SOFTWARE;

        // Prepare save request object
        $saveRequest = (new SaveRequest())
            ->setOrder($sntcOrder)
            ->setProductsType($productsType)
            ->setProducts($products)
            ->setPaymentLink($paymentLink)
            ->setInvoiceAddress($invoiceAddress)
            ->setAdditionalData($additionalData);
//            ->setTesting(true);

        // Save Order in SNTC via GetioSdk
        $saveResponse = GetioSDK::webSaveOrder($saveRequest)->send();

        if ($saveResponse->getStatus() == WebServiceTask::STATUS_DONE) {
            // Save obtained Order number and AxDlvMode
            $savedOrderData = $saveResponse->getTask()->getData()->getOrder();

            $order->setOrderNumber($savedOrderData->getNumber());
            $order->setClosedAt(Carbon::createFromFormat('Y-m-d H:i:s', $savedOrderData->getAdded()));
        } else {
            // Send Order save response data on the queue for future order save
            GetioSDK::queueSaveOrder($saveResponse->getTask()->getData())->send();
        }

        // Update Order instance
        $this->update($order);

        // Return order number
        return $order->getOrderNumber();
    }


    /**
     * Prepare Order data for save in Sntc
     *
     * @param Order $order
     * @return \GetioSDK\CustomerService\SalesOrder\Order
     */
    protected function prepareOrderToSave(Order $order)
    {
        // Get order source, visible in SNTC
        $subDomainPath = config('app.sntc_order_source');

        // Get Order currency code
        $orderCurrency = strtolower($this->getCurrencyCode());

        // Get order added Items
        $orderItems = $this->getAddedItems();

        // Get generated payment link
        $orderPaymentLink = $this->paymentService->getOrdersFirstPaymentLink($order->getId());

        // Get order hash
        $linkHash = ($orderPaymentLink == null) ? '' : $orderPaymentLink->getHash();

        // Prepare SNTC client type
        $sntcClientType = OrderAdditional::CLIENT_TYPE_END_USER_ID;

        // Get logged-in User
        $user = $this->userService->getLogged();

        $sntcManagerId = Application::getInstance()->getCspCustomer()->getAccountManager();

        // Prepare save object
        $sntcOrder = (new \GetioSDK\CustomerService\SalesOrder\Order())
            ->setCategory(0)// 0 for standard order
            ->setCategoryType($this->getSntcOrderCategoryType())
            ->setClientType($sntcClientType)
            ->setDomain('')// If subdomain is empty, SNTC will print Senetic.{{domain_from_this}}
            ->setSubdomainPath($subDomainPath)// If Subdomain is set, whole will appear in SNTC 'order from website'
            ->setLang(substr(strtolower(Lang::getLocale()), 0, 2))
            ->setAuthor($sntcManagerId)
            ->setStatus(2)// 0 - new order, 2 - draft
            ->setCurrency($orderCurrency)
            ->setCurrency1('eur')// TODO: Get real second currency code
            ->setSubtotal($order->getAmount() / 100)
            ->setSubtotal1(0)// Always 0
            ->setVatAmount(0)// Always 0
            ->setVatAmount1(0)// Always 0
            ->setTotal(0)// Always 0
            ->setTotal1(0)// Always 0
            ->setCouponCode(null)
            ->setWeborder(true)// Always true because its order from website
            ->setAuthcode('')// TODO: Get real Microsoft authorisation code
            ->setCurrencyKursDoEuro($this->exchangeService->getExchangeRate($orderCurrency, 'eur')->getRealRate())
            ->setCurrencyKursDoPln($this->exchangeService->getExchangeRate($orderCurrency, 'pln')->getRealRate())
            ->setAxPaymTerm($order->getPaymentMethod()->getAxCode())
            ->setPaymentId($order->getPaymentMethodId())
            ->setShippingId(null)
            ->setPayed(false)// Always false because order cannot be payed before save
            ->setHash($linkHash)
            ->setPaymentFee(0)// TODO: Get real payment fee. For now its always 0
            ->setPaymentFeeGross(0)// TODO: Get real payment fee. For now its always 0
            ->setPaymentTotal($order->getGrossAmount() / 100)
            ->setPaymentDeliveryFee(0)
            ->setShipping(0)// TODO: Add payment fee. For now its 0
            ->setVatRate(($this->getOrderVatRate() * 100) - 100)
            ->setInvoiceCurrency($orderCurrency)
            ->setCustomerType('Organization')
            ->setAxSalesOrderDataAreaId($this->getOrderAxCompany())
            ->setWebCompany('')
            ->setPaymentGatewayId($order->getPaymentMethod()->getGateway())
            ->setCustomerId($this->getLoggedCustomerId())
            ->setServiceDeliveryName('')
            ->setServiceDeliveryProvider('')
            ->setServiceDeliveryPrice(0)
            ->setServiceDeliveryEstimatedDate('')
            ->setServiceDeliveryWebCode('')
            ->setAxDlvMode('')
            ->setAxSendInvoiceByEmail(true)
            ->setAxAutosave(false)// No auto save for now
            ->setRDate(null)
            ->setAxSalesOrderType($this->getAxSalesOrderType($this->getOrderAxCompany()))
            ->setDeliveryWarningMessage('')
            ->setEcommerceOrderId($order->getId());

        return $sntcOrder;
    }


    /**
     * Prepare Payment link data for save in Sntc
     *
     * @param Order $order
     * @return PaymentLink
     */
    protected function preparePaymentLinkToSave(Order $order)
    {
        // Get generated payment link
        $orderPaymentLink = $this->paymentService->getOrdersFirstPaymentLink($order->getId());

        // Prepare save object
        $paymentLink = new PaymentLink();

        if ($orderPaymentLink == null) {
            return $paymentLink;
        }

        $paymentLink->setTotal($orderPaymentLink->getAmount() / 100)
            ->setFee(0)// Payment fee gross - for now always 0
            ->setFeeNet(0)// Payment fee net - for now always 0
            ->setCurrency(strtolower($orderPaymentLink->getCurrencyCode()))
            ->setHash($orderPaymentLink->getHash())
            ->setGatewayId($orderPaymentLink->getGateway());

        return $paymentLink;
    }

    /**
     * Prepare Invoice address data for save in Sntc
     *
     * @param Order $order
     * @return InvoiceAddress
     */
    protected function prepareInvoiceAddressToSave(Order $order)
    {
        // Get invoice address from order
        $orderInvoiceAddress = $order->getInvoiceAddress();

        // Prepare save object
        $invoiceAddress = (new InvoiceAddress())
            ->setCountryId($orderInvoiceAddress->getCountryId())
            ->setCountryCode($orderInvoiceAddress->getCountry()->getCode())
            ->setName($orderInvoiceAddress->getName())
            ->setSurname($orderInvoiceAddress->getSurname())
            ->setEmail($orderInvoiceAddress->getEmail())
            ->setPhonePre($orderInvoiceAddress->getPhonePre())
            ->setPhone($orderInvoiceAddress->getPhone())
            ->setStreet($orderInvoiceAddress->getStreet())
            ->setPostalCode($orderInvoiceAddress->getPostalCode())
            ->setCity($orderInvoiceAddress->getCity())
            ->setCompany($orderInvoiceAddress->getCompany())
            ->setVatPre($orderInvoiceAddress->getVatPre())
            ->setVat($orderInvoiceAddress->getVat())
            ->setElectronicInvoice($orderInvoiceAddress->getElectronicInvoice());

        return $invoiceAddress;
    }

    /**
     * Prepare Products collection data for save in Sntc
     *
     * @param Order $order
     * @return Products
     */
    protected function prepareProductsToSave(Order $order)
    {

        // Get order vat rate
        $orderVatRate = ($this->getOrderVatRate() * 100) - 100;

        // Get order currency
        $orderCurrencyCode = $order->getCurrencyCode();

        // Get collection of order items
        /** @var SoftwareItem[] $orderItems */
        $orderItems = $this->getAddedItems();

        // Get order second currency code
        $secondCurrency = 'eur';  // TODO: use real second currency

        // Prepare save object
        $products = new Products();

        if ($orderItems->count() == 0) {
            return $products;
        }

        foreach ($orderItems as $loop => $orderItem) {
            /** @var ParentSubscription $parentSubscription */
            $parentSubscription = $this->subscriptionService->getParentById($orderItem->getParentId());

            if (!$parentSubscription && $orderItem->getSubscriptionData()) {
                $parentSubscription = $this->subscriptionService->getForNewItem($orderItem);
            }

            $subscription = $this->subscriptionService->getAxSubscriptionByParent($parentSubscription->getId());

            $period = '1m';

            $offerId = substr($parentSubscription->getOfferId(), 0, 13);
            if ($parentSubscription->getSubscriptionBillingCycle() == ParentSubscription::$_BILLING_CYCLE_ANNUALY) {
                $period = '12m';
            }

            $product = (new Product())
                ->setProductId($orderItem->getItemId())
                ->setProductPartNumber($offerId)
                ->setProductQty($orderItem->getQuantity())
                ->setProductPeriodSymbol($period)
                ->setProductSubscriptionId($subscription ? $subscription->getId() : null)
                ->setProductDescription($parentSubscription->getFriendlyName())
                ->setProductPrice($orderItem->getProRate() / 100)
                ->setProductVat($orderVatRate)
                ->setProductPosition($loop + 1)
                ->setProductWwwStock(0)
                ->setProductWwwStockLevel(0)
                ->setProductWwwInStock(true)
                ->setProductWwwSendTimeID(0)
                ->setProductWwwIsSoftware(true)
                ->setProductPriceListId(0)// TODO: Get real price-list Id
                ->setProductPriceListPrice(0)// TODO: Get real price-list price
                ->setProductAutoPriceListDistributorId(0)// TODO: Get real price-list distributor Id
                ->setProductAutoPriceListDistributorPrice(0)// TODO: Get real price-list distributor price
                ->setProductAutoPriceListDistributorPriceRaw(0); // TODO: Get real price-list distributor raw price

            if ($orderItem instanceof SoftwareItem) {
                $product->setProductCSPDateStart(Carbon::now()->toDateString());
                $product->setProductCSPDateEnd($parentSubscription->getEndDate()->toDateString());
                $product->setProductPrice($orderItem->getProRate() / 100);
                $product->setProductCSPPrice($orderItem->getProRate() / 100);

                // @TODO : SEND Sunscription ID / Tenant ID
            }

            // Exchange price to secound currency
            $productPrice1 = $this->exchangeService->calculate(
                    strtolower($orderCurrencyCode),
                    $secondCurrency,
                    $orderItem->getProRate()
                ) / 100;

            $product->setProductPrice1($productPrice1);

            $products->addProduct($product);
        }

        return $products;
    }

    /**
     * @param string $offerId
     * @param int $isMonth
     * @return bool|string
     */
    protected function makePartNumberFromOfferId($offerId, $isMonth)
    {
        $string = substr((string)$offerId, 0, 13);
        // If subscription is annual - add _12m at the end
        if (!$isMonth) {
            $string .= '_12m';
        }

        return $string;
    }

    /**
     * Prepare orders additional data to save in Sntc
     *
     * @param Order $order
     * @return AdditionalData
     */
    protected function prepareAdditionalDataToSave(Order $order)
    {
        return new AdditionalData();
    }

    /**
     * Returns Sntc order category type representation of orders content
     *
     * @return int
     */
    protected function getSntcOrderCategoryType()
    {
        return 200; // Software
    }

    /**
     * Returns Ax representation of orders products
     *
     * @return string
     */
    protected function getAxSalesOrderType($axCompany = null)
    {
        //Misspell in AX for SNCH company
        if (strtoupper($axCompany) == 'SNCH') {
            return 'Licences';
        }

        return self::$_ORDER_TYPE_LICENCES;
    }

    /**
     * Sends confirmation email to the customer
     */
    public function sendCustomerConfirmationEmail()
    {
        // Get Order instance
        $order = $this->get();

        // Get Site instance and email config
        $site = $this->application->getSite();
        $siteEmailConfig = $site->getEmailConfigObject();

        // Get employee
        $employee = $order->getEmployee();

        $content = view('emails.basket.order.customer-confirmation', [
            'order' => $order,
            'siteCurrency' => $this->siteService->getCurrencyCode(),
            'companyData' => $this->contactService->getCompanyData(),
        ])->render();

        if ($employee !== null) {
            $employeeEmail = $employee->getEmail();
        } else {
            $employeeEmail = null;
        }

        $email = (new Email())
            ->setFromEmail($siteEmailConfig->getEmail())
            ->setFromName($siteEmailConfig->getEmailName())
            ->setToEmail([$order->getInvoiceAddress()->getEmail()])
            ->setSubject($siteEmailConfig->getSubjectPrefix() . trans('basket.order.confirmation-email.your_order_received'))
            ->setReplyTo($employeeEmail)
            ->setContent($content);

        GetioSDK::queueSendMail($email)->send();
    }

    /**
     * Sends confirmation email to the responsible for order employee
     */
    public function sendEmployeeConfirmationEmail()
    {
        // Get Order Instance
        $order = $this->get();

        // Get Site instance and email config
        $site = $this->application->getSite();
        $siteEmailConfig = $site->getEmailConfigObject();

        // Get employee
        $employee = $order->getEmployee();

        $content = view('emails.basket.order.employee-confirmation', [
            'order' => $order,
            'siteCurrency' => $this->siteService->getCurrencyCode(),
        ])->render();

        if ($employee !== null) {
            $employeeEmail = $employee->getEmail();

            $email = (new Email())
                ->setFromEmail($siteEmailConfig->getEmail())
                ->setFromName($siteEmailConfig->getEmailName())
                ->setToEmail([$employeeEmail])
                ->setSubject($siteEmailConfig->getSubjectPrefix() . 'Potwierdzenie przyjęcia zamówienia')
                ->setReplyTo($employeeEmail)
                ->setContent($content);

            // TODO: uncomment while running new Senetic online
            //GetioSDK::queueSendMail($email)->send();
        }
    }

    /*
       ____  ________  ____________ 
      / __ \/_  __/ / / / ____/ __ \
     / / / / / / / /_/ / __/ / /_/ /
    / /_/ / / / / __  / /___/ _, _/ 
    \____/ /_/ /_/ /_/_____/_/ |_|  
    */

    /**
     * Sets vies status
     *
     * @param integer $statusCode
     */
    public function setViesStatus($statusCode)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setViesStatus((int)$statusCode);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Return vies status code
     *
     * @return integer
     */
    public function getViesStatus()
    {
        return $this->get()
            ->getViesStatus();
    }

    /**
     * Return order number
     *
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->get()
            ->getOrderNumber();
    }

    /**
     * Sets order additional information
     *
     * @param OrderAdditional $orderAdditionalData
     */
    public function setAdditional(OrderAdditional $orderAdditionalData)
    {
        // Get Order Instance
        $order = $this->get();

        // If end user address has not been set already save obtained address and set its ID to the order
        $oldAdditionalData = $this->getAdditional();
        if (empty($oldAdditionalData->getId())) {
            $orderAdditionalData->save();

            $order->setOrderAdditionalsId($orderAdditionalData->getId());
        } else {
            // There has been set end user address already - overwrite its data
            $newAdditionalData = $orderAdditionalData->toArray();

            $oldAdditionalData->updateFromArray($newAdditionalData);
            $oldAdditionalData->save();
        }

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns additional data set for the Order
     *
     * @return OrderAdditional
     */
    public function getAdditional()
    {
        // Get Order Instance
        $order = $this->get();

        // Get additional data by additional data ID hold in Order
        $orderAdditionalId = (integer)$order->getOrderAdditionalsId();
        $orderAdditional = OrderAdditional::where('id', $orderAdditionalId)->first();

        if (!empty($orderAdditional)) {
            return $orderAdditional;
        }

        // Return empty OrderAdditional by default
        return (new OrderAdditional());
    }

    /**
     * Sets chosen by customer delivery method's id to the order
     *
     * @param int $deliveryMethodId
     * @param int $deliveryCost
     * @param string $deliveryTime
     */
    public function setDeliveryMethod($deliveryMethodId, $deliveryCost, $deliveryTime)
    {
        $vatRate = $this->getOrderVatRate();

        // Get Order Instance
        $order = $this->get();
        $order->setDeliveryMethodId((int)$deliveryMethodId);
        $order->setDeliveryTime((string)$deliveryTime);

        $deliveryNetCost = $this->roundService->round($deliveryCost, true, RoundService::DELIVERY_NET);
        $order->setDeliveryCost($deliveryNetCost);
        $order->setGrossDeliveryCost($this->roundService->round(round((int)$deliveryNetCost * $vatRate, 0), false, RoundService::DELIVERY_GROSS));

        $this->calculateGrossPrices();

        // Update Order instance
        $this->update($order);
    }

    /**
     * Sets chosen by customer payment method's id to the order
     *
     * @param integer $paymentMethodId
     */
    public function setPaymentMethod($paymentMethodId)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setPaymentMethodId((int)$paymentMethodId);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Sets given by the customer discount code to the order
     *
     * @param string $discountCode
     */
    public function setDiscountCode($discountCode)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setDiscountCode((string)$discountCode);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns set discount code
     *
     * @return string
     */
    public function getDiscountCode()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getDiscountCode();
    }

    /**
     * Returns Coupon set to Order or null if none or invalid
     *
     * @return \App\Module\Coupon\Container\Coupon\CategoryFixedValueCoupon|\App\Module\Coupon\Container\Coupon\CategoryPercentageCoupon|\App\Module\Coupon\Container\Coupon\DeliveryFixedValueCoupon|\App\Module\Coupon\Container\Coupon\DeliveryForCategoryPercentageCoupon|\App\Module\Coupon\Container\Coupon\DeliveryPercentageCoupon|\App\Module\Coupon\Container\Coupon\ProductBundleGratisProductCoupon|\App\Module\Coupon\Container\Coupon\ProductBundleSetFixedValueCoupon|\App\Module\Coupon\Container\Coupon\ProductFixedValueCoupon|\App\Module\Coupon\Container\Coupon\WholeCartFixedValueCoupon|\App\Module\Coupon\Container\Coupon\WholeCartPercentageCoupon|null
     */
    public function getCoupon()
    {
        if (!empty($this->getDiscountCode()) && $this->isCouponValid()) {
            return $this->couponService->loadByCode($this->getDiscountCode());
        }

        return null;
    }


    /**
     * @return int
     */
    public function getDiscount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getDiscount();
    }

    /**
     * @param int $discount
     * @return $this
     */
    public function setDiscount($discount)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setDiscount((int)$discount);

        // Update Order instance
        $this->update($order);
    }

    /**
     * @return int
     */
    public function getGrossDiscount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getGrossAmount();
    }

    /**
     * @param int $grossDiscount
     * @return $this
     */
    public function setGrossDiscount($grossDiscount)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setGrossDiscount((int)$grossDiscount);

        // Update Order instance
        $this->update($order);
    }

    /**
     * @return int
     */
    public function getDeliveryDiscount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getDeliveryDiscount();
    }

    /**
     * @param int $deliveryDiscount
     * @return $this
     */
    public function setDeliveryDiscount($deliveryDiscount)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setDeliveryDiscount((int)$deliveryDiscount);

        // Update Order instance
        $this->update($order);
    }

    /**
     * @return int
     */
    public function getGrossDeliveryDiscount()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getGrossDeliveryDiscount();
    }

    /**
     * @param int $grossDeliveryDiscount
     * @return $this
     */
    public function setGrossDeliveryDiscount($grossDeliveryDiscount)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setGrossDeliveryDiscount((int)$grossDeliveryDiscount);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns total order discount net amount
     *
     * @return int
     */
    public function getTotalDiscount()
    {
        return $this->getDiscount() + $this->getDeliveryDiscount();
    }

    /**
     * Returns total order discount gross amount
     *
     * @return int
     */
    public function getTotalGrossDiscount()
    {
        return $this->getGrossDiscount();
    }

    /**
     * Clears discount values on all order items
     */
    public function clearItemsDiscount()
    {
        $items = $this->getItems();

        foreach ($items as $item) {
            $item->setPriceDiscount(0)
                ->setGrossPriceDiscount(0)
                ->setAmountDiscount(0)
                ->setGrossAmountDiscount(0)
                ->save();

            // Calculate CSP Item Pro Rate
            if ($item->isCspMonthItem()) {
                $item->getSoftwareItemCsp()
                    ->setProRateDiscount(0)
                    ->setGrossProRateDiscount(0)
                    ->setAmountProRateDiscount(0)
                    ->setGrossAmountProRateDiscount(0)
                    ->save();
            }
        }
    }

    /**
     * Sets employee that will be responsible for Order
     *
     * @param Employee $employee
     */
    public function setEmployee(Employee $employee)
    {
        // Get Order Instance
        $order = $this->get();

        $order->setEmployee($employee);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns Employee responsible for Order
     *
     * @return Employee|null
     */
    public function getEmployee()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getEmployee();
    }

    /**
     * Calculates vat rate according to current order state and check its blocked status
     */
    public function calculateVatRate()
    {
        // Get Order Instance
        $order = $this->get();

        // Set default blocked status
        $order->setBlocked(self::$BLOCKED_NONE);

        // Set default vat rate - site vat rate
        $defaultVatRate = $this->vatService->getVatRate(VatConfig::$VAT_RATE_SOURCE_SITE);
        $order->setVatRate($defaultVatRate);

        // Update Order instance
        $this->update($order);

        // Check if order is blocked due to license restrictions
        if (in_array($this->getItemsType(), [self::$ITEMS_TYPE_SOFTWARE, self::$ITEMS_TYPE_MIX]) && $this->getAdditional()->getCustomerType() == OrderAdditional::CUSTOMER_TYPE_INDIVIDUAL) {
            $order->setBlocked(self::$BLOCKED_LICENSE);
        }

        // Get VAT matrix response
        $vatConfig = $this->vatService->matrix();

        if (!$vatConfig instanceof VatConfig) {
            return;
        }

        // Check if order is blocked due to vies configuration
        if ($vatConfig->isBlocked()) {
            $order->setBlocked(self::$BLOCKED_VIES);
        }

        // Get new vat rate
        $vatRate = $this->vatService->getVatRate($vatConfig->getVatRateSource());

        // Set new vat rate to the Order
        $order->setVatRate($vatRate);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns vat rate actually assigned to Order
     *
     * @return float
     */
    public function getOrderVatRate()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getVatRate();
    }

    /**
     * Returns vat rate actually assigned to Order
     *
     * @return float
     */
    public function getOrderVatRatePercentage()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getVatRatePercentage();
    }

    /**
     * Sets AxCompany to the Order
     *
     * @param string $axCompanyCode
     */
    public function setOrderAxCompany($axCompanyCode)
    {
        // Get Order Instance
        $order = $this->get();

        $order->setAxCompany((string)$axCompanyCode);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns AxCompany that Order is assigned to
     *
     * @return string
     */
    public function getOrderAxCompany()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getAxCompany();
    }

    /**
     * Returns AxCompany that Order is assigned to
     *
     * @return string
     */
    public function getOrderAxCompanyFullName()
    {
        // Get Order Instance
        $order = $this->get();

        $companyCode = $order->getAxCompany();

        $companyContact = $this->contactService->getCompanyDataForCode($companyCode);

        if (!$companyContact instanceof Contact) {
            return '';
        }

        return $companyContact->getCompanyName();
    }


    /**
     * Returns Order's currency code
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        $order = $this->get();

        return $order->getCurrencyCode();
    }

    /**
     * Sets coupon error message to the Order
     *
     * @param string $error
     */
    public function setCouponError($error)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setCouponError((string)$error);

        // Update Order instance
        $this->update($order);
    }

    /**
     * Returns coupon error message from the Order
     *
     * @return string
     */
    public function getCouponError()
    {
        // Get Order Instance
        $order = $this->get();

        return $order->getCouponError();
    }

    /**
     * Sets status of coupon validity to the Order
     *
     * @param bool $isValid
     */
    public function setIsCouponValid($isValid)
    {
        // Get Order Instance
        $order = $this->get();
        $order->setIsCouponValid((bool)$isValid);

        // Update Order instance
        $this->update($order);
    }

    /**
     * @return bool
     */
    public function isCouponValid()
    {
        // Get Order Instance
        $order = $this->get();

        return (bool)$order->getIsCouponValid();
    }

    /**
     * Returns set to the Order coupon code or empty string if none has been set
     *
     * @return string
     */
    public function getCouponCode()
    {
        if (!$this->isCouponValid()) {
            return '';
        }

        return $this->getCoupon()->getCode();
    }

    /**
     * Recalculates all order items and summary gross prices
     */
    public function calculateGrossPrices()
    {
        // Get vat rate
        $vatRate = $this->getOrderVatRate();
        $totalGrossAmount = 0;

        // Get order software items
        $orderSoftwareItems = $this->getSoftwareItems();

        foreach ($orderSoftwareItems as $orderItem) {
            $item = $orderItem->getItem();
            $quantity = $orderItem->getQuantity();

            $grossAmount = $this->softwareService->calculateGrossAmount($item, $quantity, $vatRate);
            $grossPrice = $this->softwareService->calculateGrossAmount($item, 1, $vatRate);

            $totalGrossAmount += $grossAmount;

            // Calculate CSP Item Pro Rate
            if ($orderItem->isCspMonthItem()) {
                $this->cspService->calculateItemProRate($orderItem, true);
            }

            $orderItem->setGrossAmount($grossAmount);
            $orderItem->setGrossPrice($grossPrice);
            $orderItem->save();
        }

        $this->refresh();

        // Get order
        $order = $this->get();

        $totalGrossAmount += $order->getGrossDeliveryCost();

        // Set new total gross Amount
        $order->setGrossAmount($totalGrossAmount);

        // Update order
        $this->update($order);
    }

    /**
     * @param $orderNumber
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getOrderByOrderNumber($orderNumber)
    {
        return Order::where('order_number', $orderNumber)
            ->first();
    }

    /**
     * Change status and quantity of Subscription
     * in Microsoft account
     * (Only for confirmed actions from order)
     *
     * @param Order $order
     * @param bool $removed
     * @throws \GetioCSP\Exception\AccountException
     * @throws \GetioCSP\Exception\SubscriptionException
     * @throws \Throwable
     */
    public function updateMicrosoftSubscriptionData(Order $order, $removed = false)
    {

        // If sandbox then don't update MS
        if (env('PAYMENT_SANDBOX', null)) {
            return null;
        }

        if ($removed) {
            $orderItems = $this->softwareService->getOrderRemovedItems($order);
        } else {
            $orderItems = $this->softwareService->getItemsFromOrder($order);
        }

        $newSubscriptionsItems = [];
        /** @var ParentSubscription[] $newParentSubscriptions */
        $newParentSubscriptions = [];

        /** @var AxCustomer $customer */
        $customer = $this->application->getCspCustomer();

        foreach ($orderItems as $orderItem) {
            if ($orderItem->getParentId()) {
                $subscription = $this->subscriptionService->getAxSubscriptionByParent($orderItem->getParentId());

                if ($subscription->getQuantity() == 0) {
                    $this->subscriptionService->suspend($subscription);

                } else {
                    $microsoftAccount = $this->accountService->getByName($subscription->getDomain());

                    if ($subscription->getStatus() == Subscription::$_STATUS_SUSPENDED) {
                        $this->microsoftSubscriptionService->reactiveSubscription($subscription);
                        $this->subscriptionService->changeStatus($subscription->getId(), SubscriptionStatus::$_ACTIVE);
                    }

                    $this->application->setMicrosoftAccount($microsoftAccount);

                    $this->changeQuantitySubscription($subscription, $subscription->getQuantity() + $orderItem->getQuantity());

                    // Get data from ParentIdTable for tenant id info
                    $parentIdTable = DB::connection('ms_sql2')->table('CSPParentIdTable')->where('Id', $subscription->getId())->first();

                    // Call our api to update subscriptions in dboview
                    $data = [
                        'customerId' => $subscription->getCustomerId(),
                        'subscriptionId' => $subscription->getId(),
                        'tenantId' => $parentIdTable->MicrosoftId
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://solutionunion1.azurewebsites.net/api/solutionunion/addsubscription');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen(json_encode($data)),
                        'Authorization: Basic ' . base64_encode(config('azureapi.user') . ':' . config('azureapi.password'))
                    ]);
                    $result = curl_exec($ch);

                    curl_close($ch);
                }
            } else if (!$orderItem->getParentId()) {
                // new subscriptions
                $data = json_decode($orderItem->getSubscriptionData(), true, 512, JSON_BIGINT_AS_STRING);

                $newSubscriptionsItems[] = [
                    'LineItemNumber' => count($newSubscriptionsItems),
                    'OfferId' => $orderItem->getOfferId(),
                    'SubscriptionId' => null,
                    'ParentSubscriptionId' => null,
                    'FriendlyName' => $data['name'],
                    'Quantity' => $orderItem->getQuantity(),
                ];

                if ($data['billingCycle'] == ParentSubscription::$_BILLING_CYCLE_MONTHLY) {
                    $endDate = Carbon::now()->endOfMonth();
                } else {
                    $endDate = Carbon::now()->addYear();
                }

                $newParentSubscriptions[] = (new ParentSubscription())
                    ->setOfferId($orderItem->getOfferId())
                    ->setCspCustomerId($customer->getId())
                    ->setName($data['name'])
                    ->setSubscriptionBillingCycle($data['billingCycle'])
                    ->setStartDate(Carbon::now())
                    ->setEndDate($endDate)
                    ->setStatus('Active')
                    ->setQuantity($orderItem->getQuantity())
                    ->setAutoRenew(false)
                    ->setBillingType($data['billingType']);
            }
        }

        // new subscriptions
        // Call our api to update subscriptions in dboview
        $data = [
            'Id' => null,
            'ReferenceCustomerId' => $customer->getId(),
            'BillingCycle' => 'annual',
            'LineItems' => $newSubscriptionsItems,
            'CreationDate' => null,
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://senetic4.azurewebsites.net/v1/customers/' . $customer->getId() . '/orders');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,  JSON_BIGINT_AS_STRING));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data, JSON_BIGINT_AS_STRING)),
            'Authorization: Basic c2VuZXRpY2FwaTpAcDJaRnpHWWtMb21qaCp1'
        ]);
        $result = curl_exec($ch);

        curl_close($ch);
    }

    /**
     * @param AxSubscription $subscription
     * @param int $quantity
     * @return SubscriptionResponse
     * @throws \GetioSDK\IntegrationService\Microsoft\Partner\Exception\MicrosoftPartnerCenterException
     */
    public function changeQuantitySubscription($subscription, $quantity)
    {
        $request = (new QuantitySubscriptionRequest())
            ->setTenantId($subscription->getCustomerId())
            ->setSubscriptionId($subscription->getId())
            ->setQuantity((int) $quantity)
            ->setMicrosoftAccount($this->getMicrosoftAccount());

        $service = GetioSDK::webQuantitySubscription($request);

        $response = $service->send();

        return SubscriptionResponse::parse($response);
    }

    /**
     * @return array
     */
    public function checkUnpaidPiaOrders()
    {
        // Get list of orders which we should check
        $softwareItems = SoftwareItem::where('confirm', '1')
            ->groupBy('order_id')
            ->get();

        $checkOrders = $this->getOrdersToCheck($softwareItems);

        $ordersToCheck = $checkOrders['ordersToCheck'];
        $orderCollection = $checkOrders['orderCollection'];

        // No orders to check = finish
        if (!count($ordersToCheck)) {
            return ['status' => 'No orders to check'];
        }

        // We send request to CustomerServer to check those orders
        $request = (new OrderPaymentsRequest())
            ->setOrderNumbers($ordersToCheck);

        $response = GetioSDK::webCheckOrderPayments($request)->send();

        if ($response->getStatus() != AbstractTask::STATUS_DONE) {
            return ['error' => 'Error while sending request to customer server'];
        }

        // It return list of orders that was not paid so we should take licences back
        $orders = $response->getTask()->getData()->getOrders();

        // Here we change every order
        return $this->suspendUnpaidOrders($orders, $orderCollection);
    }

    /**
     * @param array $softwareItems
     * @return array
     */
    protected function getOrdersToCheck($softwareItems)
    {
        $ordersToCheck = [];
        $orderCollection = [];
        foreach ($softwareItems as $softwareItem) {
            // Get only finished orders which exist in SNTC (have order_number)
            $order = Order::where('id', $softwareItem->getOrderId())
                ->whereNotNull('order_number')
                ->first();

            if ($order) {
                // Add order to list only If it's older then 7 days
                if (Carbon::now()->diffInDays($order->created_at) > 7) {
                    $ordersToCheck[] = $order->getOrderNumber();
                    $orderCollection[$order->getOrderNumber()] = $order->getId();
                }
            }
        }

        return [
            'ordersToCheck' => $ordersToCheck,
            'orderCollection' => $orderCollection
        ];
    }

    /**
     * @param array $orders
     * @param array $orderCollection
     * @return array
     */
    protected function suspendUnpaidOrders($orders, $orderCollection)
    {
        foreach ($orders as $order) {
            try {
                // Find all CSP items from this order
                $softwareItems = SoftwareItem::where('order_id', $orderCollection[$order->getNumber()])
                    ->get();

                if ($softwareItems) {
                    foreach ($softwareItems as $softwareItem) {
                        // Get csp subscription
                        $cspSubscription = Subscription::where('id', $softwareItem->getSoftwareItemCsp()->getSubscriptionId())->first();

                        // Get Microsoft account
                        $cspAccount = Account::where('id', $cspSubscription->getCspAccountId())->first();

                        // Set MicrosoftAccount for current subscription
                        $this->application->setMicrosoftAccount($cspAccount);

                        // Get quantity we should suspend
                        $quantity = $cspSubscription->getQuantity() - $softwareItem->getQuantity();

                        // Send request to Microsoft
                        $this->microsoftSubscriptionService->changeQuantitySubscription($cspSubscription, $quantity);

                        // Change quantity in local DB
                        $cspSubscription->setQuantity($quantity);
                        $cspSubscription->save();
                    }
                }
            } catch (\Exception $e) {
                return ['error' => $e->getMessage()];
            }
        }

        return ['Status' => 'Has successfully finished'];
    }

    /**
     * @param SoftwareItem $orderItem
     * @return int
     */
    public function getOrderItemPrice(SoftwareItem $orderItem)
    {
        // Get this subscription and check if it has locked price
        $subscription = $this->subscriptionService->getSubscriptionById($orderItem->getSoftwareItemCsp()->getSubscriptionId());
        $parentSubscription = $this->subscriptionService->getCustomerParentSubscriptionByOfferId($subscription->getCspCustomerId(), $subscription->getOfferId());

        if ((!$parentSubscription instanceof ParentSubscription) || !$parentSubscription->getHasLockedPrice()) {
            return $orderItem->getPrice();
        }

        // If has - check if it's still up to date and currency is the same
        $currency = $this->getById($orderItem->getOrderId())->getCurrencyCode();
        $lockedPrice = $parentSubscription->getLockedPrice();
        $isUpToDate = Carbon::now() > $lockedPrice->getEndDate() ? 0 : 1;

        if ($lockedPrice->getCurrency() != $currency || !$isUpToDate) {
            return $orderItem->getPrice();
        }

        return $lockedPrice->getPrice();

    }

    /**
     * @param ParentSubscription $parentSubscription
     * @return int
     */
    public function getSubscriptionPrice(ParentSubscription $parentSubscription)
    {
        // Check if this subscription has locked price
        $customer = $this->application->getCspCustomer();
        $country = $this->countryService->getByIso($customer->getCountry());

        // Dziala - tabela CSPPriceList z Bisnode
        $item = $this->itemService->getAccurateItem($parentSubscription->getOfferId(), $country->getCurrencyCode(), $country->getExchangeCurrencyCode());

        //  tabela item_prices
//        $item = $this->itemService->getTemporaryAccurateItem($parentSubscription->getOfferId(), $country->getExchangeCurrencyCode());

        if (!$item instanceof Item) {
            return null;
        }

        //Reveiced ammount is per month
        $price = $item->getPrice();
        $itemPriceCurrency = $item->getCurrencyCode();

        if ($parentSubscription->getHasLockedPrice()) {
            $lockedPrice = $parentSubscription->getLockedPrice();

            if ($lockedPrice instanceof LockedPrice) {
                $price = $lockedPrice->getPrice();
                $itemPriceCurrency = $lockedPrice->getCurrency();
            }
        }

        $priceList = $this->priceListService->get($country->getPriceListId());

        if (!$priceList instanceof PriceList) {
            return null;
        }

        $redisRateKey = $priceList->getCurrencyCode() . '_' . $country->getCurrencyCode();
        $billingcycle = PriceRuleMarkup::mapBillingCycle($parentSubscription->getSubscriptionBillingCycle());
        $staticLine = $this->staticPriceListService->getAccurateLine($priceList->getStaticPriceListId(), $item->getOfferId(), $billingcycle);

        if ($staticLine instanceof StaticPriceListLine) {

            $price = $staticLine->getPrice();

            if ($staticLine->getPriceType() == StaticPriceListLine::PRICE_TYPE_PERCENT) {
                $percentage = $staticLine->getPrice() / 100;
                $price = $this->roundService->round($item->getPrice() * $percentage, true, RoundService::SEPARATE_NET, $country->getCode());
            }

            $price = $staticLine->getPrice();

        } else {
            $PriceRuleMarkup = $this->priceListService->getAccurateMarkup($priceList->getPriceRuleId(), PriceRuleMarkup::mapBillingCycle($parentSubscription->getSubscriptionBillingCycle()), PriceRuleMarkup::$_LICENSE_TYPE_ALL);
            $price *= $PriceRuleMarkup->getMarkup();

            if (strtolower($country->getCurrencyCode()) != strtolower($priceList->getCurrencyCode())) {
                $rate = Redis::get($redisRateKey);

                if (!$rate) {
                    $conversion = (new Conversion())
                        ->setFrom($priceList->getCurrencyCode())
                        ->setTo($country->getCurrencyCode())
                        ->setValue(10000);

                    $request = (new ConvertRequest())
                        ->setConversion($conversion);

                    $response = GetioSDK::webPriceConversion($request)->send();

                    if (($response->getStatus() == AbstractTask::STATUS_DONE)) {
                        $rate = $response->getTask()->getData()->getConvertedValue() / 10000;

                        Redis::set($redisRateKey, $rate);
                        Redis::expire($redisRateKey, 300);
                    }
                }

                $price *= $rate;
            }

            if ($parentSubscription->getSubscriptionBillingCycle() == ParentSubscription::$_BILLING_CYCLE_ANNUALY) {
                $price *= 12;
            }
        }

        return $price;
    }

    /**
     * @param int $priceNet
     * @param int $quantity
     * @param string $billingCycle
     * @param Carbon $dateEnd
     * @return int
     */
    public function calcProRate($priceNet, $quantity, $billingCycle, Carbon $dateEnd)
    {
        $diffDays = 365;

        if ($billingCycle == ParentSubscription::$_BILLING_CYCLE_MONTHLY) {
            $diffDays = Carbon::now()->daysInMonth;
        }

        $pricePerDay = $priceNet / $diffDays;
        $proRate = $pricePerDay * (Carbon::now()->startOfDay()->diffInDays($dateEnd->endOfDay()) + 1);

        return (int)bcmul($proRate, 1);
    }

    /**
     * @param Order $order
     * @return Collection
     * @throws \GetioCSP\Exception\SubscriptionException
     */
    public function getAnnualParentSubscriptions(Order $order)
    {
        $softwareItems = $this->softwareService->getOrderRemovedItems($order);
        $parentSubscriptions = new Collection();

        foreach ($softwareItems as $softwareItem) {
            $parentSubscription = ParentSubscription::where('id', $softwareItem->getParentId())->first();
            $parentSubscriptions->put($parentSubscription->getName() . '#' . $softwareItem->getQuantity(), $parentSubscription);
        }
        return $parentSubscriptions->filter(function (ParentSubscription $parentSubscription) {
            if ($parentSubscription->getSubscriptionBillingCycle() == ParentSubscription::$_BILLING_CYCLE_ANNUALY) {
                return true;
            }

            return false;
        });
    }

    /**
     * @param int $parentId
     * @return int|null
     */
    public function getItemQuantityByParent($parentId)
    {
        $orderItem = $this->softwareService->getCurrentItemByParentId($parentId);

        if (!$orderItem instanceof SoftwareItem) {
            return null;
        }
        return $orderItem->getQuantity();
    }


}
