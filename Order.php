<?php

namespace App\Module\Basket\Model\Order;

use App\Module\Basket\Model\Item\Hardware\HardwareItem;
use App\Module\Basket\Model\Order\Address\AccessPointSourceAddress;
use App\Module\Basket\Model\Order\Address\UpsAccessPoint;
use App\Module\Basket\Model\Order\DeliveryMethod\Method as DeliveryMethod;
use App\Module\Basket\Model\Order\PaymentMethod\Method as PaymentMethod;
use App\Module\Basket\Model\Order\Address\OrderDeliveryAddress;
use App\Module\Basket\Model\Order\Address\OrderEndUserAddress;
use App\Module\Basket\Model\Order\Address\OrderInvoiceAddress;
use App\Module\Basket\Model\Item\Software\SoftwareItem;
use Illuminate\Database\Eloquent\Collection;
use App\Module\Cache\Database\CacheModel;
use App\Module\Employee\Model\Employee;
use App\Module\Core\Model\Currency;
use App\Module\Auth\Model\User;
use App\Module\Core\Model\Site;
use Carbon\Carbon;


/**
 * Class Order
 * @package App\Module\Basket\Model\Order
 */
class Order extends CacheModel
{
    /**
     * @var string
     */
    public static $_SOFTWARE = 'software';

    /**
     * SNTC unpaid order status
     *
     * @var int
     */
    const STATUS_UPNAID = 999;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'closed_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function softwareItems()
    {
        return $this->hasMany(SoftwareItem::class, 'order_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoiceAddress()
    {
        return $this->belongsTo(OrderInvoiceAddress::class, 'invoice_address_id', 'id');
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = (int)$id;

        return $this;
    }

    /**
     * @return int
     */
    public function getOrderNumber()
    {
        return $this->order_number;
    }

    /**
     * @param int $orderNumber
     * @return $this
     */
    public function setOrderNumber($orderNumber)
    {
        $this->order_number = (int)$orderNumber;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getClosed()
    {
        return $this->closed;
    }

    /**
     * @param boolean $closed
     * @return $this
     */
    public function setClosed($closed)
    {
        $this->closed = (bool)$closed;

        return $this;
    }

    /**
     * @return bool
     */
    public function isCosed()
    {
        return (bool)$this->getClosed();
    }

    /**
     * @return int
     */
    public function getPaymentMethodId()
    {
        return $this->payment_method_id;
    }

    /**
     * @param int $paymentMethodId
     * @return $this
     */
    public function setPaymentMethodId($paymentMethodId)
    {
        $this->payment_method_id = (int)$paymentMethodId;

        return $this;
    }

    /**
     * @return \App\Module\Basket\Model\Order\PaymentMethod\Method
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * @param PaymentMethod $paymentMethod
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function setPaymentMethod(PaymentMethod $paymentMethod)
    {
        return $this->paymentMethod()
            ->associate($paymentMethod);
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->getCurrency()
            ->getCode();
    }

    /**
     * @param Currency $currency
     * @return $this
     */
    public function setCurrency(Currency $currency)
    {
        $this->currency()
            ->associate($currency);

        return $this;
    }

    /**
     * @return string
     */
    public function getAxCompany()
    {
        return $this->ax_company;
    }

    /**
     * @param string $axCompany
     * @return $this
     */
    public function setAxCompany($axCompany)
    {
        $this->ax_company = (string)$axCompany;

        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->user_id = (int)$userId;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user)
    {
        return $this->user()
            ->associate($user);
    }

    /**
     * @return int
     */
    public function getInvoiceAddressId()
    {
        return $this->invoice_address_id;
    }

    /**
     * @param int $invoiceAddressId
     * @return $this
     */
    public function setInvoiceAddressId($invoiceAddressId)
    {
        $this->invoice_address_id = (int)$invoiceAddressId;

        return $this;
    }

    /**
     * @return OrderInvoiceAddress|null
     */
    public function getInvoiceAddress()
    {
        return $this->invoiceAddress;
    }

    /**
     * @param OrderInvoiceAddress $invoiceAddress
     * @return $this
     */
    public function setInvoiceAddress(OrderInvoiceAddress $invoiceAddress)
    {
        return $this->invoiceAddress()
            ->associate($invoiceAddress);
    }

    /**
     * @return string
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @param string $session
     * @return $this
     */
    public function setSession($session)
    {
        $this->session = (string)$session;

        return $this;
    }

    /**
     * @return float
     */
    public function getVatRate()
    {
        return $this->vat_rate;
    }

    /**
     * @param float $vatRate
     * @return $this
     */
    public function setVatRate($vatRate)
    {
        $this->vat_rate = (float)$vatRate;

        return $this;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = (int)$amount;

        return $this;
    }

    /**
     * @return int
     */
    public function getGrossAmount()
    {
        return $this->gross_amount;
    }

    /**
     * @param int $grossAmount
     * @return $this
     */
    public function setGrossAmount($grossAmount)
    {
        $this->gross_amount = (int)$grossAmount;

        return $this;
    }

    /**
     * @return Carbon
     */
    public function getClosedAt()
    {
        return $this->closed_at;
    }

    /**
     * @param Carbon $closedAt
     * @return $this
     */
    public function setClosedAt(Carbon $closedAt)
    {
        $this->closed_at = $closedAt;

        return $this;
    }

    /**
     * Returns collection of specific item type collections
     *
     * @return Collection
     */
    public function getItems()
    {
        if (!$this->relationLoaded('items')) {
            $this->setItems($this->reloadItems());
        }

        return $this->items;
    }

    /**
     * @param Collection $items
     * @return $this
     */
    public function setItems(Collection $items)
    {
        $this->setRelation('items', $items);

        return $this;
    }

    /**
     * @return Collection
     */
    public function reloadItems()
    {
        $softwareItems = $this->softwareItems;

        $items = new Collection();
        $items->put(self::$_SOFTWARE, $softwareItems);

        return $items;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->countSoftware();
    }

    /**
     * @return int
     */
    public function countAdd()
    {
        if (!$this->getItems()->has(self::$_SOFTWARE)) {
            return 0;
        }

        $quantity = 0;

        foreach ($this->getItems()->get(self::$_SOFTWARE) as $item) {
            if (!$item->isReturn()) {
                $quantity += $item->getQuantity();
            }
        }

        return $quantity;
    }

    /**
     * @return int
     */
    public function countRemove()
    {
        if (!$this->getItems()->has(self::$_SOFTWARE)) {
            return 0;
        }

        $quantity = 0;

        foreach ($this->getItems()->get(self::$_SOFTWARE) as $item) {
            if ($item->isReturn()) {
                $quantity += $item->getQuantity();
            }
        }

        return $quantity;
    }

    /**
     * @return int
     */
    public function countSoftware()
    {
        if (!$this->getItems()->has(self::$_SOFTWARE)) {
            return 0;
        }

        $quantity = 0;

        foreach ($this->getItems()->get(self::$_SOFTWARE) as $item) {
            $quantity += $item->getQuantity();
        }

        return $quantity;
    }

    /**
     * @return int
     */
    public function countSoftwareItems()
    {

        if (!$this->getItems()->has(self::$_SOFTWARE)) {
            return 0;
        }

        return $this->getItems()->get(self::$_SOFTWARE)->count();
    }

    /**
     * @return SoftwareItem[]
     */
    public function getSoftwareItems()
    {
        return $this->getItems()
            ->get(self::$_SOFTWARE);
    }

    /**
     * @return int
     */
    public function getVatRatePercentage()
    {
        return ($this->vat_rate - 1) * 100;
    }

    /**
     * @return int
     */
    public function getTaxAmount()
    {
        return $this->getGrossAmount() - $this->getAmount();
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }
}
