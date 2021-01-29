<?php

namespace RethinkIT\RedisCart;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use RethinkIT\RedisCart\Exceptions\InvalidRowIDException;

class RedisCart 
{
    const DEFAULT_INSTANCE = 'default';

    private $instance;
    private $associatedModel = null;

    public $key;
    public $cartId;

    public function __construct()
    {
        $this->instance(self::DEFAULT_INSTANCE);

        if (!auth()->check() && !session()->get('guestId')) {
            session()->put('guestId', uniqid());
        }

        // dd(config('rediscart.redis_cart_multiple_store'));
    }

    public function instance($instance = null)
    {
        $this->instance = $instance ?: self::DEFAULT_INSTANCE;
        $this->key = sprintf('%s.%s', config('app.env').$this->instance, auth()->check() ? auth()->user()->id : session()->get('guestId'));

        return $this;
    }

    public function currentInstance()
    {
        return str_replace('rediscart.', '', $this->instance);
    }

    public function get($cartId)
    {
        $content = $this->getContent();

        if ( ! $content->has($cartId))
            throw new InvalidRowIDException("The cart does not contain cartId {$cartId}.");

        return $content->get($cartId);
    }

    public function getContent()
    {
        return collect(json_decode(Redis::get($this->key), true)) ?: new Collection;
    }

    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {
        
        $content = $this->getContent();
        $this->cartId = $this->generateRowId($id, $options);

        $cartItem = $this->createCartItem($id, $name, $qty, $price, $options);

        if ($content->has($cartItem->cartId)) {
            $cartItem->qty += $content->get($cartItem->cartId)['qty'];
        }

        $content[$this->cartId] = $cartItem;

        Redis::set($this->key, json_encode($content));
    }

    public function update($cartId, $qty)
    {
        $content = $this->getContent();

        $cartItem = $this->get($cartId);

        if ($content->has($cartId)) {
            $cartItem['qty'] += $qty;
        }

        $content[$cartId] = $cartItem;

        Redis::set($this->key, json_encode($content));
    }

    public function remove($cartId)
    { 
        $cartItem = $this->get($cartId);

        $content = $this->getContent();

        $content = $content->forget($cartId);

        Redis::set($this->key, json_encode($content));
    }

    public function empty()
    {
        Redis::flushDB();
    }

    public function createCartItem($id, $name, $qty, $price, array $options)
    {
        if(empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if(empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }
        if(strlen($price) < 0 || ! is_numeric($price)) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }

        return (object) [
            'cartId' => $this->generateRowId($id, $options),
            'productId' => $id,
            'name' => $name,
            'qty' => $qty,
            'price' => $price,
            'options' => $options
        ];
    }
    
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }
    
}
