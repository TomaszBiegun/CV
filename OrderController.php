<?php

namespace App\Module\Basket\Controller;

use App\Module\Basket\Model\Order\PaymentLink;
use App\Module\Basket\Model\Order\PaymentMethod\Translation;
use App\Module\Basket\Service\BankAccountService;
use App\Module\Basket\Service\Item\Software\SoftwareService;
use App\Module\Basket\Service\PaymentService;
use App\Module\Basket\Workflow\AddressesBoxReloadWorkflow;
use App\Module\Basket\Workflow\OrderPaymentWorkflow;
use App\Module\Basket\Workflow\OrderThankYouWorkflow;
use App\Module\Basket\Workflow\OrderCheckoutWorkflow;
use App\Module\Basket\Workflow\OrderSummaryWorkflow;
use App\Module\Basket\Model\Order\OrderAdditional;
use App\Module\Contact\Service\CompanyService;
use App\Module\Core\Controller\AbstractController;
use App\Module\Core\Directives\RoundDirective;
use App\Module\Core\Service\CountryService;
use App\Module\Core\Service\CurrencyService;
use App\Module\Core\Service\LogService;
use App\Module\Core\Service\RoundService;
use GetioCSP\Model\ParentSubscription;
use GetioCSP\Service\SubscriptionService;
use App\Module\Basket\Service\DeliveryService;
use App\Module\Basket\Service\OrderService;
use App\Module\Product\Service\Item\ItemService;
use App\Module\Basket\Model\Order\Order;
use App\Module\Basket\Request\OrderForm;
use App\Module\Core\Service\SiteService;
use App\Module\Core\Service\UrlService;
use function GuzzleHttp\Promise\queue;
use Illuminate\Http\Request;
use App\Module\Core\Application\Application;
use Illuminate\Support\Collection;


/**
 * Class OrderController
 *
 * @package App\Module\Basket\Controller
 */
class OrderController extends AbstractController
{
    /**
     * @var ItemService|null
     */
    protected $itemService = null;

    /**
     * @var OrderService|null
     */
    protected $orderService = null;

    /**
     * @var SiteService|null
     */
    protected $siteService = null;

    /**
     * @var DeliveryService|null
     */
    protected $deliveryService = null;

    /**
     * @var UrlService|null
     */
    protected $urlService = null;

    /**
     * @var null|SubscriptionService
     */
    protected $subscriptionService = null;

    /**
     * @var null|BankAccountService
     */
    protected $bankAccountService = null;

    /**
     * @var null|CompanyService
     */
    protected $companyService = null;

    /**
     * @var null|LogService
     */
    protected $logService = null;

    /**
     * @var null|SoftwareService
     */
    protected $softwareService = null;

    /**
     * @var null|PaymentService
     */
    protected $paymentService = null;

    /**
     * @var CountryService|null
     */
    protected $countryService = null;

    /**
     * @var CurrencyService|null
     */
    protected $currencyService = null;

    /**
     * @inheritdoc
     */
    public function getServices()
    {
        $this->orderService = $this->serviceManager
            ->get(OrderService::class);

        $this->siteService = $this->serviceManager
            ->get(SiteService::class);

        $this->deliveryService = $this->serviceManager
            ->get(DeliveryService::class);

        $this->urlService = $this->serviceManager
            ->get(UrlService::class);

        $this->itemService = $this->serviceManager
            ->get(ItemService::class);

        $this->subscriptionService = $this->serviceManager
            ->get(SubscriptionService::class);

        $this->bankAccountService = $this->serviceManager
            ->get(BankAccountService::class);

        $this->companyService = $this->serviceManager
            ->get(CompanyService::class);

        $this->logService = $this->serviceManager
            ->get(LogService::class);

        $this->softwareService = $this->serviceManager
            ->get(SoftwareService::class);

        $this->paymentService = $this->serviceManager
            ->get(PaymentService::class);

        $this->countryService = $this->serviceManager
            ->get(CountryService::class);

        $this->currencyService = $this->serviceManager
            ->get(CurrencyService::class);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Module\Basket\Exception\InvalidItemQuantityException
     * @throws \Exception
     * @throws \Throwable
     */
    public function subscriptionSetItemQuantity(Request $request)
    {
        $parentId = (string)$request->get('parentId');
        $offerId = (string)$request->get('offerId');
        $quantity = (int)$request->get('quantity');
        $type = (string)$request->get('type');

        $customer = $this->application->getCspCustomer();
        $country = $this->countryService->getByIso($customer->getCountry());
        $item = $this->itemService->getAccurateItem($offerId, $country->getExchangeCurrencyCode());

        $parentSubscription = $this->subscriptionService->getParentById($parentId);
        $newParent = false;

        if (!$parentSubscription instanceof ParentSubscription) {
            if ($parentId) {
                return false;
            }

            $parentSubscription = new ParentSubscription();
            $newParent = true;
        }

        $quantity -= $parentSubscription->getQuantity();

        if ($quantity) {
            $this->orderService->subscriptionSetItemQuantity($item, $quantity, $parentSubscription->getId(), true, $newParent, $offerId, $type);
        } else {
            $this->softwareService->removeOrderItemByParentId($parentSubscription->getId());
        }
        $this->orderService->refresh();

        $cartBox = view('cart.cartbox', [
            'orderService' => $this->orderService,
        ])->render();

        return response()->json([
            'orderCartBox' => $cartBox,
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @throws \Throwable
     */
    public function cartAddItem(Request $request)
    {
        $quantity = (int)$request->get('quantity');
        $partNumber = (string)$request->get('part_number');

        $item = $this->getOrderItem($partNumber);

        $orderItem = $this->orderService
            ->addItem($item, $quantity, null);

        $cartBox = view('cart.cartbox', [
            'orderService' => $this->orderService,
        ])->render();

        $orderTable = view('order.partials.order_table', [
            'addItems' => $this->orderService->getAddItems(),
            'removeItems' => $this->orderService->getRemoveItems(),
        ])->render();


        return response()->json([
            'orderCartBox' => $cartBox,
            'orderTable' => $orderTable
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @throws \Throwable
     */
    public function cartRemoveItem(Request $request)
    {
        $orderItemId = (string)$request->get('orderItemId');
        $orderItem = $this->softwareService->getById($orderItemId);

        if ($orderItem != null) {
            $this->softwareService->remove($orderItem);
        }

        $this->orderService->reload();

        $cartBox = view('cart.cartbox', [
            'orderService' => $this->orderService,
        ])->render();

        $orderTable = view('order.partials.order_table', [
            'subscriptionService' => $this->subscriptionService,
            'currencyCode' => $this->orderService->getCurrencyCode(),
            'orderService' => $this->orderService,
            'addItems' => $this->orderService->getAddItems(),
            'removeItems' => $this->orderService->getRemoveItems(),
            'siteCurrency' => $this->siteService->getCurrencyCode(),
        ])->render();

        return response()->json([
            'orderCartBox' => $cartBox,
            'orderTable' => $orderTable
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Module\Basket\Exception\InvalidItemQuantityException
     * @throws \Throwable
     */
    public function cartSetItemQuantity(Request $request)
    {
        $partNumber = (string)$request->get('part_number');
        $prefix = (string)$request->get('prefix');
        $quantity = (int)$request->get('quantity');
        $subscriptionId = (string)$request->get('subscriptionId');

        $customer = $this->application->getCspCustomer();
        $item = $this->getOrderItem($partNumber);
        $orderItem = $this->orderService->getItem($item, $subscriptionId);

        if ($orderItem->isReturn()) {
            // return - check quantity

            $subscription = $this->subscriptionService
                ->getByIdAndTenantId($subscriptionId, $customer->getId());

            if ($subscription->getQuantity() < $quantity) {
                $quantity = $subscription->getQuantity();
            }
        }

        $orderItem = $this->orderService
            ->setItemQuantity($item, $quantity, $subscriptionId, false);

        $cartBox = view('cart.cartbox', [
            'orderService' => $this->orderService,
        ])->render();

        $orderTable = view('order.partials.order_table', [
            'orderService' => $this->orderService,
            'addItems' => $this->orderService->getAddItems(),
            'removeItems' => $this->orderService->getRemoveItems(),
            'siteCurrency' => $this->siteService->getCurrencyCode(),
        ])->render();

        return response()->json([
            'orderCartBox' => $cartBox,
            'orderTable' => $orderTable
        ]);
    }

    /**
     * @return bool
     */
    public function deleteCurrentOrder()
    {
        return $this->orderService->deleteCurrentOrder();
    }

    /**
     * Orders first step - data gather page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function checkout()
    {
        // Check if order exists and has items
        if (!$this->orderService->get() || !$this->orderService->getItems()->count() || $this->orderService->countSoftwareItems() == 0) {
            return redirect(route('dashboard'));
        }

        $this->orderService->refresh();

        // Process workflow
        (new OrderCheckoutWorkflow())->process();

        $order = $this->orderService->get();
        $currency = $this->currencyService->get($order->getCurrencyId());

        // Display the view
        return view('order.checkout', [
            'orderService' => $this->orderService,
            'subscriptionService' => $this->subscriptionService,
            'currencyCode' => $currency->getCode(),
            'siteCountry' => $this->siteService->getCountry(),
            'pageClass' => 'checkout',
        ]);
    }

    /**
     * Orders second step - summary page
     *
     * @param OrderForm $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function summary(OrderForm $request)
    {
        // Check if order exists and has items
        if (!$this->orderService->get() || !$this->orderService->getItems()->count() || $this->orderService->countSoftwareItems() == 0) {
            return redirect(route('dashboard'));
        }

        // Process workflow
        (new OrderSummaryWorkflow())->process();

        $order = Application::getInstance()->getOrder();
        $order->country = Application::getInstance()->getCspCustomer()->getCountry();
        $order->save();

        $order = $this->orderService->get();
        $currency = $this->currencyService->get($order->getCurrencyId());
        // Display the view
        return view('order.summary', [
            'orderService' => $this->orderService,
            'subscriptionService' => $this->subscriptionService,
            'currencyCode' => $currency->getCode(),
            'siteCountry' => $this->siteService->getCountry(),
            'pageClass' => 'checkout',
        ]);
    }

    /**
     * Orders third step - payment page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function payment()
    {
        // Check if order exists and has items
        if (!$this->orderService->get() || !$this->orderService->getItems()->count() || $this->orderService->countSoftwareItems() == 0) {
            return redirect(route('dashboard'));
        }

        // Process workflow
        (new OrderPaymentWorkflow())->process([
                'user' => Application::getInstance()->getUser()
            ]
        );

        // Close order
        $this->orderService->close($this->orderService->get());

        return view('order.payment', [
            'pageClass' => 'checkout payment_step',
        ]);
    }

    /**
     * Orders step 4 - thank You page
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function thankYou()
    {
        if ($this->orderService->countSoftwareItems() == 0) {
            return view('order.checkout-empty');
        }

        // Process workflow
        (new OrderThankYouWorkflow())->process();

        $price = $this->orderService->getGrossAmount();

        // Close current order
        $order = $this->orderService->get();
        $currency = $this->currencyService->get($order->getCurrencyId());

//        $this->logService->getOrderAction();

        $this->orderService->close($order);

        // Get data for Bank Transfer
        $company = $this->companyService->getCompanyData();
        $bankAccount = $this->bankAccountService->getForOrder($order);

        $parentSubscriptions = $this->orderService->getAnnualParentSubscriptions($order);

        // Display the view
        return view('order.thank-you', [
            'pageClass' => 'checkout',
            'bankAccount' => $bankAccount,
            'company' => $company,
            'currencyCode' => $currency->getCode(),
            'price' => $price,
            'parentSubscriptions' => $parentSubscriptions,
            'paymentMethod' => $order->getPaymentMethod()->getAxCode()
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function box()
    {
        return view('headers.partials.cartbox', [
            'orderService' => $this->orderService,
        ]);
    }

    /**
     * @param string $partNumber
     * @return \App\Module\Product\Model\Item|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    protected function getOrderItem($partNumber)
    {
        $partNumber = $this->itemService->offerIdToPartNumber((string)$partNumber);
        $item = $this->itemService
            ->getByPartNumber($partNumber);

        if ($item !== null) {
            return $item;
        }

        return $this->itemService
            ->getByCspPartNumber($partNumber);
    }

}
